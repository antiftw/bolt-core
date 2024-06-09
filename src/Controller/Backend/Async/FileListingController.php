<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend\Async;

use Bolt\Configuration\Config;
use Bolt\Utils\FilesIndex;
use Bolt\Utils\PathCanonicalize;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileListingController implements AsyncZoneInterface
{
    private Request $request;
    private string $publicPath;

    public function __construct(
        private readonly FilesIndex $filesIndex,
        private readonly Config $config,
        RequestStack $requestStack,
        string $projectDir,
        string $publicFolder,

    ) {
        $this->request = $requestStack->getCurrentRequest();
        $this->publicPath = $projectDir . DIRECTORY_SEPARATOR . $publicFolder;
    }

    #[Route('/list_files', name: 'bolt_async_filelisting', methods: ['GET'])]
    #[IsGranted('list_files')]
    public function index(): JsonResponse
    {
        $locationName = $this->request->query->get('location', 'files');
        $type = $this->request->query->get('type', '');
        $locationTopLevel = explode('/', Path::canonicalize($locationName))[0];

        // @todo: config->getPath does not return the correct relative URL.
        // Hence, we use the Path::makeRelative. Fix this once config generates the correct relative path.
        $relativeLocation = Path::makeRelative($this->config->getPath($locationName, false), $this->publicPath);
        $relativeTopLocation = Path::makeRelative($this->config->getPath($locationTopLevel, false), $this->publicPath);

        // Do not allow any path outside the public directory.
        $path = PathCanonicalize::canonicalize($this->publicPath, $relativeLocation);
        $baseFilePath = PathCanonicalize::canonicalize($this->publicPath, $relativeTopLocation);
        $baseUrlPath = $this->request->getPathInfo();
        $relativePath = Path::makeRelative($path, $this->publicPath);

        $files = $this->filesIndex->get($relativePath, $type, $baseUrlPath, $baseFilePath);

        return new JsonResponse($files);
    }
}
