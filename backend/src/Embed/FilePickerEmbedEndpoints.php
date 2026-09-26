<?php

namespace App\Embed;

use Rocket\Core\Embed\EmbedEndpointsInterface;

/**
 * The embedded file picker (/embed/picker, see public/embed.js) browses the folders and files of its user and reads
 * their content, and nothing else: no upload, no change, no share link.
 */
final class FilePickerEmbedEndpoints implements EmbedEndpointsInterface
{
    public function embedEndpoints(): iterable
    {
        yield ['GET', '#^/api/folders(/[^/]+)?$#'];
        yield ['GET', '#^/api/files$#'];
        yield ['GET', '#^/api/files/usage$#'];
        yield ['GET', '#^/api/files/[^/]+$#'];
        yield ['GET', '#^/api/files/[^/]+/content$#'];
    }
}
