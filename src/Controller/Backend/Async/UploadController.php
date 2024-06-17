<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend\Async;

use Bolt\Common\Str;
use Bolt\Configuration\Config;
use Bolt\Controller\CsrfTrait;
use Bolt\Factory\MediaFactory;
use Bolt\Twig\TextExtension;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sirius\Upload\Handler;
use Sirius\Upload\Result\Collection;
use Sirius\Upload\Result\ResultInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

#[Security('is_granted("upload")')]
class UploadController extends AbstractController implements AsyncZoneInterface
{
    use CsrfTrait;
    private Request $request;

    public function __construct(
        private readonly MediaFactory           $mediaFactory,
        private readonly EntityManagerInterface $em,
        private readonly Config                 $config,
        private readonly TextExtension          $textExtension,
        private readonly Filesystem             $filesystem,
        private readonly TagAwareCacheInterface $cache,
                         RequestStack           $requestStack,
    ) {
        $this->request = $requestStack->getCurrentRequest();
    }

    #[Route('/upload-url', name: 'bolt_async_upload_url', methods: ['POST'])]
    public function handleURLUpload(Request $request): Response
    {
        try {
            $this->validateCsrf('upload');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Invalid CSRF token',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $url = $request->get('url', '');
        $filename = basename($url);

        $locationName = $request->get('location', '');
        $path = $request->get('path') . $filename;
        $folderPath = $this->config->getPath($locationName, true, 'tmp/');
        $target = $this->config->getPath($locationName, true, 'tmp/' . $path);

        try {
            // Make sure temporary folder exists
            $this->filesystem->mkdir($folderPath);
            // Create temporary file
            $this->filesystem->copy($url, $target);
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => [
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $file = new UploadedFile($target, $filename);
        $bag = new FileBag();
        $bag->add([$file]);
        $request->files = $bag;

        $response = $this->handleUpload($request);

        // The file is automatically deleted. It may be that we don't need this.
        $this->filesystem->remove($target);

        return $response;
    }

    /**
     * @Route("/upload", name="bolt_async_upload", methods={"POST"})
     */
    public function handleUpload(Request $request): JsonResponse
    {
        try {
            $this->validateCsrf('upload');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Invalid CSRF token',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', '');
        $path = $this->request->query->get('path', '');

        $basepath = $this->config->getPath($locationName);
        $target = $this->config->getPath($locationName, true, $path);

        // Make sure we don't move it out of the root.
        if (Str::startsWith(path::makeRelative($target, $basepath), '../')) {
            return new JsonResponse([
                'error' => [
                    'message' => "You are not allowed to do that.",
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $uploadHandler = new Handler($target, [
            Handler::OPTION_AUTOCONFIRM => true,
            Handler::OPTION_OVERWRITE => false,
        ]);

        $acceptedFileTypes = array_merge($this->config->getMediaTypes()->toArray(), $this->config->getFileTypes()->toArray());
        $maxSize = $this->config->getMaxUpload();

        $uploadHandler->addRule(
            'extension',
            ['allowed' => $acceptedFileTypes],
            'The file for field \'{label}\' was <u>not</u> uploaded. It should be a valid file type. Allowed are <code>' . implode('</code>, <code>', $acceptedFileTypes) . '.',
            'Upload file'
        );

        $uploadHandler->addRule(
            'size',
            ['size' => $maxSize],
            'The file for field \'{label}\' was <u>not</u> uploaded. The upload can have a maximum filesize of <b>' . $this->textExtension->formatBytes($maxSize) . '</b>.',
            'Upload file'
        );

        $uploadHandler->addRule(
            'callback',
            ['callback' => [$this, 'checkJavascriptInSVG']],
            'It is not allowed to upload SVG\'s with embedded Javascript.',
            'Upload file'
        );

        $uploadHandler->setSanitizerCallback(function ($name) {
            return $this->sanitiseFilename($name);
        });

        // Clear the 'files_index' cache.
        $this->cache->invalidateTags(['fileslisting']);

        try {
            /** @var UploadedFile|File|ResultInterface|Collection $result */
            $result = $uploadHandler->process($request->files->all());
        } catch (Throwable $e) {
            return new JsonResponse([
                'error' => [
                    'message' => $e->getMessage() . ' Ensure the upload does <em><u>not</u></em> exceed the maximum filesize of <b>' . $this->textExtension->formatBytes($maxSize) . '</b>, and that the destination folder (on the webserver) is writable.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($result->isValid()) {
            try {
                // TODO: CHECK THIS
                $media = $this->mediaFactory->createFromFilename($locationName, $path, $result->__get('name'));

                if ($this->mediaFactory->isImage($media)) {
                    $this->em->persist($media);
                    $this->em->flush();
                }

                return new JsonResponse($media->getFilenamePath());
            } catch (Throwable $e) {
                // something wrong happened, we don't need the uploaded files anymore
                $result->clear();

                throw $e;
            }
        }

        // image was not moved to the container, where are error messages
        $messages = $result->getMessages();

        return new JsonResponse([
            'error' => [
                'message' => implode(', ', $messages),
            ],
        ], Response::HTTP_BAD_REQUEST);
    }

    private function sanitiseFilename(string $filename): string
    {
        $extensionSlug = new Slugify(['regexp' => '/([^a-z0-9]|-)+/']);
        $filenameSlug = new Slugify(['lowercase' => false]);

        $extension = $extensionSlug->slugify(Path::getExtension($filename));
        $filename = $filenameSlug->slugify(Path::getFilenameWithoutExtension($filename));

        return $filename . '.' . $extension;
    }

    public function checkJavascriptInSVG($file): bool
    {
        if (Path::getExtension($file['name']) != 'svg') {
            return true;
        }

        $svgFile = file_get_contents($file['tmp_name']);

        if (preg_match('/<[^>]+\s(on\S+)=["\']?((?:.(?!["\']?\s+\S+=|[>"\']))+.)["\']?/i', $svgFile)) {
            return false;
        }

        return (mb_strpos(preg_replace('/\s+/', '', mb_strtolower($svgFile)), '<script') === false);
    }
}

