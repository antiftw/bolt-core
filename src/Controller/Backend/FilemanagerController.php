<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Common\Str;
use Bolt\Configuration\FileLocations;
use Bolt\Controller\CsrfTrait;
use Bolt\Controller\TwigAwareController;
use Bolt\Repository\MediaRepository;
use Bolt\Utils\Excerpt;
use Bolt\Utils\PathCanonicalize;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

class FilemanagerController extends TwigAwareController implements BackendZoneInterface
{
    use CsrfTrait;
    private const int PAGE_SIZE = 60;

    public function __construct(
        private readonly FileLocations $fileLocations,
        private readonly MediaRepository $mediaRepository,
        private readonly RequestStack $requestStack,
        private readonly Filesystem $filesystem
    ) {}

    #[Route('/filemanager/{location}', name: 'bolt_filemanager', methods: ['GET'])]
    public function filemanager(string $location): Response
    {
        $session = $this->requestStack->getSession();

        $this->denyAccessUnlessGranted('managefiles:' . $location);

        $path = $this->getFromRequest('path', '');
        if (str::endsWith($path, '/') === false) {
            $path .= '/';
        }
        if (str::startsWith($path, '/') === false) {
            $path = '/' . $path;
        }

        if ($this->getFromRequest('view')) {
            $view = $this->getFromRequest('view') === 'cards' ? 'cards' : 'list';
            $session->set('filemanager_view', $view);
        } else {
            $view = $this->getFromRequest('filemanager_view', 'list');
        }

        $location = $this->fileLocations->get($location);

        $finder = $this->findFiles($location->getBasePath(), $path);
        $folders = $this->findFolders($location->getBasePath(), $path);

        $currentPage = (int) $this->getFromRequest('page', '1');
        $pager = $this->createPaginator($finder, $currentPage);

        $parent = $path !== '/' ? Path::canonicalize($path . '/..') : '';

        return $this->render('@bolt/finder/finder.html.twig', [
            'path' => $path,
            'location' => $location,
            'finder' => $pager,
            'folders' => $folders,
            'parent' => $parent,
            'media' => $this->mediaRepository->findAll(),
            'allfiles' => $location->isShowAll() ? $this->buildIndex($location->getBasePath()) : false,
            'view' => $view,
        ]);
    }

    #[Route('/filemanager-actions/delete', name: 'bolt_filemanager_delete', methods: ['POST', 'GET'])]
    public function delete(): Response
    {
        try {
            $this->validateCsrf('filemanager-delete');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Invalid CSRF token',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $path = $this->getFromRequest('path');
        $location = $this->getFromRequest('location');

        $this->denyAccessUnlessGranted('managefiles:' . $location);

        $location = $this->fileLocations->get($location);

        $folder = Path::canonicalize($location->getBasePath() . '/' . $path);

        if (! $this->filesystem->exists($folder)) {
            $this->addFlash('warning', 'filemanager.delete_folder_missing');
        } else {
            try {
                $this->filesystem->remove($folder);
                $this->addFlash('success', 'filemanager.delete_folder_successful');
            } catch (IOException $e) {
                $this->addFlash('danger', 'filemanager.delete_folder_error');
            }
        }

        return $this->redirectToRoute('bolt_filemanager', [
            'location' => $this->getFromRequest('location'),
            'path' => Path::canonicalize($path . '/..'),
        ]);
    }

    #[Route('/filemanager-actions/create', name: 'bolt_filemanager_create', methods: ['POST'])]
    public function create(): Response
    {
        try {
            $this->validateCsrf('filemanager-create');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Invalid CSRF token',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $path = $this->getFromRequest('path') . $this->getFromRequest('folderName');
        $location = $this->getFromRequest('location');

        $this->denyAccessUnlessGranted('managefiles:' . $location);

        $location = $this->fileLocations->get($location);

        $folder = Path::canonicalize($location->getBasePath() . '/' . $path);

        if ($this->filesystem->exists($folder)) {
            $this->addFlash('warning', 'filemanager.create_folder_already_exists');
            $this->addFlash('danger', 'filemanager.create_folder_error');
        } else {
            try {
                $this->filesystem->mkdir($folder);
                $this->addFlash('success', 'filemanager.create_folder_success');
            } catch (IOException $exception) {
                $this->addFlash('danger', 'filemanager.create_folder_error');
            }
        }

        return $this->redirectToRoute('bolt_filemanager', [
            'location' => $this->getFromRequest('location'),
            'path' => Path::canonicalize($path . '/..'),
        ]);
    }

    private function findFiles(string $base, string $path): Finder
    {
        $fullPath = PathCanonicalize::canonicalize($base, $path);

        $finder = new Finder();
        $finder->in($fullPath)->depth('== 0')->files()->sortByName();

        return $finder;
    }

    private function findFolders(string $base, string $path): Finder
    {
        $fullPath = PathCanonicalize::canonicalize($base, $path);

        $finder = new Finder();
        $finder->in($fullPath)->depth('== 0')->directories()->sortByName();

        return $finder;
    }

    private function createPaginator(Finder $finder, int $page): Pagerfanta
    {
        $paginator = new Pagerfanta(new ArrayAdapter(iterator_to_array($finder)));
        $paginator->setMaxPerPage(self::PAGE_SIZE);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    private function buildIndex(string $base): array
    {
        $fullPath = Path::canonicalize($base);

        $finder = new Finder();
        $finder->in($fullPath)->depth('< 5')->sortByName()->files();

        $index = [];

        foreach ($finder as $file) {
            $contents = $this->getFileSummary($file->getContents());
            $index[] = [
                'filename' => $file->getRelativePathname(),
                'description' => $contents,
            ];
        }

        return $index;
    }

    private function getFileSummary($contents): string
    {
        $contents = str_replace(['<?php', '# ', "\n"], ['', '', " \n"], $contents);

        return Excerpt::getExcerpt($contents, 300);
    }
}
