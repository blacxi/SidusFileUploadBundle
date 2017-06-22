<?php

namespace Sidus\FileUploadBundle\Controller;

use Sidus\FileUploadBundle\Model\ResourceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UnexpectedValueException;

/**
 * Expose a download link for uploaded resources
 *
 * @author Vincent Chalnot <vincent@sidus.fr>
 */
class FileController extends Controller
{
    /**
     * @param string     $type
     * @param string|int $identifier
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function downloadAction($type, $identifier)
    {
        return $this->getStreamedResponse($type, $identifier);
    }

    /**
     * @param string     $type
     * @param string|int $identifier
     *
     * @return StreamedResponse|NotFoundHttpException
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws \RuntimeException
     * @throws \League\Flysystem\FileNotFoundException
     * @throws UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function getStreamedResponse($type, $identifier)
    {
        $resourceManager = $this->get('sidus_file_upload.resource.manager');
        /** @var ResourceInterface $resource */
        $resource = $resourceManager->getRepositoryForType($type)->find($identifier);

        $fs = $resourceManager->getFilesystem($resource);
        if (!$fs->has($resource->getPath())) {
            throw $this->createNotFoundException("File not found {$resource->getPath()} ({$type})");
        }

        $originalFilename = $resource->getPath();
        if ($resource) {
            $originalFilename = $resource->getOriginalFileName();
        }


        $stream = $fs->readStream($resource->getPath());
        if (!$stream) {
            throw new \RuntimeException("Unable to open stream to resource {$resource->getPath()}");
        }

        $response = new StreamedResponse(
            function () use ($stream) {
                while (!feof($stream)) {
                    echo fread($stream, 512);
                }
                fclose($stream);
            }, 200
        );

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                'attachment',
                $originalFilename,
                $this->transliterate($originalFilename)
            )
        );
        $response->headers->set('Content-Type', $fs->getMimetype($resource->getPath()));

        return $response;
    }

    /**
     * @param string $originalFilename
     *
     * @return string
     */
    protected function transliterate($originalFilename)
    {
        $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        $string = $transliterator->transliterate($originalFilename);

        return trim(
            preg_replace(
                '/[^\x20-\x7E]/',
                '_',
                $string
            ),
            '_'
        );
    }
}
