<?php

namespace App\Controller;

use App\Entity\Folder;
use App\Entity\StoredFile;
use App\Files\FileStorage;
use App\Files\FileUploader;
use App\Repository\FolderRepository;
use App\Repository\StoredFileRepository;
use Rocket\Core\Security\ActorContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

final class FileController extends AbstractController
{
    /**
     * Multipart upload: field "file", optional "folder" (id) and "name". Applications upload on behalf of a user
     * (X-Impersonate-User), e.g. a generated document.
     */
    #[Route('/api/files', name: 'api_file_upload', methods: ['POST'])]
    public function upload(
        #[MapUploadedFile([new Assert\NotNull()])] UploadedFile $file,
        Request $request,
        FileUploader $uploader,
        FolderRepository $folders,
        ActorContext $actor,
    ): JsonResponse {
        $user = $actor->requireUser();
        $folderId = $request->request->getString('folder');
        $folder = '' === $folderId ? null : $folders->find(preg_replace('#^/api/folders/#', '', $folderId));
        if ('' !== $folderId && (null === $folder || $folder->getOwner() !== $user)) {
            return $this->json(['detail' => 'This folder does not exist.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $stored = $uploader->upload($file, $user, $folder, $request->request->getString('name') ?: null);

        return $this->json($stored, Response::HTTP_CREATED, context: ['groups' => ['file:read', 'tracking']]);
    }

    /** The content: inline for previewable types, else (or with ?download=1) as an attachment. */
    #[Route('/api/files/{id}/content', name: 'api_file_content', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function content(StoredFile $file, Request $request, FileStorage $storage, ActorContext $actor): BinaryFileResponse
    {
        if ($file->getOwner() !== $actor->requireUser()) {
            throw $this->createNotFoundException();
        }

        return self::send($file, $storage, $request->query->getBoolean('download') || !$file->isPreviewable());
    }

    /**
     * Replaces the content of a file, keeping its id, name, folder and share links (a document edited by another
     * application, for instance): raw body with PUT, or multipart "file" with POST. Applications replace on behalf
     * of the owner (X-Impersonate-User).
     */
    #[Route('/api/files/{id}/content', name: 'api_file_replace', requirements: ['id' => Requirement::UUID], methods: ['PUT', 'POST'])]
    public function replace(StoredFile $file, Request $request, FileUploader $uploader, ActorContext $actor): JsonResponse
    {
        if ($file->getOwner() !== $actor->requireUser()) {
            throw $this->createNotFoundException();
        }
        $upload = $request->files->get('file');
        if ($upload instanceof UploadedFile) {
            if (!$upload->isValid()) {
                return $this->json(['detail' => $upload->getErrorMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $content = $upload;
        } else {
            $path = tempnam(sys_get_temp_dir(), 'replace');
            $body = $request->getContent(true);
            $out = fopen($path, 'w');
            stream_copy_to_stream($body, $out);
            fclose($out);
            $content = new File($path);
        }
        if (0 === $content->getSize()) {
            return $this->json(['detail' => 'The new content is empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($uploader->replace($file, $content), context: ['groups' => ['file:read', 'tracking']]);
    }

    /** Storage used by the current user, quota and upload limit. */
    #[Route('/api/files/usage', name: 'api_file_usage', methods: ['GET'], priority: 10)]
    public function usage(StoredFileRepository $files, FileUploader $uploader, ActorContext $actor): JsonResponse
    {
        $user = $actor->requireUser();

        return $this->json([
            'used' => $files->usage($user),
            'quota' => $uploader->quota(),
            'maxFileSize' => $uploader->maxFileSize(),
            'files' => $files->count(['owner' => $user]),
        ]);
    }

    public static function send(StoredFile $file, FileStorage $storage, bool $attachment): BinaryFileResponse
    {
        $path = $storage->path($file);
        if (!is_file($path)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('The file content is missing.');
        }
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $attachment ? 'application/octet-stream' : $file->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Files are never run as pages of the API origin.
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox");
        $response->setContentDisposition(
            $attachment ? ResponseHeaderBag::DISPOSITION_ATTACHMENT : ResponseHeaderBag::DISPOSITION_INLINE,
            $file->getName(),
            FileUploader::sanitizeName(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $file->getName()) ?: 'fichier'),
        );

        return $response;
    }
}
