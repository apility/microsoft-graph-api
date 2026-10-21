<?php

namespace Microsoft\GraphAPI\Filesystem;

use RuntimeException;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

use Microsoft\GraphAPI\Exceptions\GraphAPIException;
use Microsoft\GraphAPI\Facades\GraphAPI;
use Microsoft\GraphAPI\Filesystem\Models\DriveItem;

use Psr\Http\Message\StreamInterface;

class Adapter implements FilesystemAdapter
{
    const MAX_CHUNK_SIZE = 1024 * 1024 * 4;

    protected string $site_id = '';

    public function __construct(array $config)
    {
        $this->site_id = $config['site_id'] ?? '';
    }

    protected function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        $path = $path ? sprintf(':/%s:', $path) : '';
        $path = sprintf('/sites/%s/drive/root%s', $this->site_id, $path);

        return $path;
    }

    /**
     * @param string $path
     * @return DriveItem|null
     */
    protected function getDriveItem(string $path): ?DriveItem
    {
        try {
            return DriveItem::getByPath($this->normalizePath($path));
        } catch (GraphAPIException $e) {
            return null;
        }
    }

    protected function createDriveItem(string $path, string $type): DriveItem
    {
        $path = trim($path, '/');
        $parentPath = dirname($path);
        $parentPath = $parentPath === '.' ? '' : $parentPath;
        $parent = $this->getDriveItem($parentPath);

        $name = basename($path);

        if (!$name) {
            throw new RuntimeException('Invalid path');
        }

        $payload = [
            'name' => basename($path),
            '@microsoft.graph.conflictBehavior' => 'fail',
        ];

        if ($type === DriveItem::TYPE_FOLDER) {
            $payload['folder'] = (object) [];
        } else {
            $payload['file'] = (object) [];
        }

        GraphAPI::post(
            sprintf('/sites/%s/drive/items/%s/children', $this->site_id, $parent->id()),
            $payload,
        );

        return $this->getDriveItem($path);
    }

    protected function getDriveItemUrl(DriveItem $item): string
    {
        $path = sprintf('/sites/%s/drive/items/%s/content', $this->site_id, $item->id());

        $response = GraphAPI::request()
            ->withOptions(['allow_redirects' => false])
            ->get($path);

        return $response->header('Location');
    }

    protected function getDriveItemContents(DriveItem $item): StreamInterface
    {
        $path = sprintf('/sites/%s/drive/items/%s/content', $this->site_id, $item->id());

        $response = GraphAPI::request()
            ->withOptions(['allow_redirects' => ['strict' => true]])
            ->get($path);

        return $response->toPsrResponse()
            ->getBody();
    }

    protected function deleteDriveItem(DriveItem $item): bool
    {
        try {
            $path = sprintf('/sites/%s/drive/items/%s', $this->site_id, $item->id());

            GraphAPI::delete($path);

            return true;
        } catch (GraphAPIException $e) {
            return false;
        }
    }

    protected function updateDriveItem(DriveItem $item, array $payload): bool
    {
        try {
            $path = sprintf('/sites/%s/drive/items/%s', $this->site_id, $item->id());

            GraphAPI::patch($path, $payload);

            return true;
        } catch (GraphAPIException $e) {
            return false;
        }
    }

    /**
     * @param string $path
     * @return DriveItem[]
     */
    protected function listDriveItems(string $path): array
    {
        $path = sprintf('%s/children', $this->normalizePath($path));

        return DriveItem::listByPath($path);
    }

    /**
     * @param string $path
     * @return DriveItem[]
     */
    protected function listDriveItemsRecursive(string $path): array
    {
        $items = $this->listDriveItems($path);

        foreach ($items as $item) {
            if ($item->type() === DriveItem::TYPE_FOLDER && $item->childCount() > 0) {
                $items = array_merge(
                    $items,
                    $this->listDriveItemsRecursive($item->path())
                );
            }
        }

        return array_values($items);
    }

    protected function normalizeDirname(string $path): string
    {
        $dirname = dirname($path);

        return ltrim($dirname, '/');
    }

    protected function normalizeDriveItem(DriveItem $item): StorageAttributes
    {
        $path = ltrim($item->path(), '/');
        $timestamp = $item->lastModifiedDateTime()->getTimestamp();

        if ($item->type() === DriveItem::TYPE_FOLDER) {
            return new DirectoryAttributes($path, 'public', $timestamp);
        }

        return new FileAttributes($path, $item->size(), 'public', $timestamp, $item->mimeType());
    }

    /**
     * Metadata for a file or folder. Folders report the mimetype "directory",
     * which consumers use to tell the two apart.
     */
    protected function attributes(string $path, string $type): FileAttributes
    {
        $item = $this->getDriveItem($path);

        if ($item === null) {
            throw UnableToRetrieveMetadata::create($path, $type, 'Drive item not found');
        }

        return new FileAttributes(
            ltrim($item->path(), '/'),
            $item->size(),
            'public',
            $item->lastModifiedDateTime()->getTimestamp(),
            $item->mimeType()
        );
    }

    public function fileExists(string $path): bool
    {
        $item = $this->getDriveItem($path);

        return $item !== null && $item->type() === DriveItem::TYPE_FILE;
    }

    public function directoryExists(string $path): bool
    {
        $item = $this->getDriveItem($path);

        return $item !== null && $item->type() === DriveItem::TYPE_FOLDER;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        throw UnableToWriteFile::atLocation($path, 'Writing files is not supported');
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        throw UnableToWriteFile::atLocation($path, 'Writing files is not supported');
    }

    public function read(string $path): string
    {
        $item = $this->getDriveItem($path);

        if ($item === null || $item->type() !== DriveItem::TYPE_FILE) {
            throw UnableToReadFile::fromLocation($path, 'File not found');
        }

        return (string) $this->getDriveItemContents($item);
    }

    public function readStream(string $path)
    {
        $item = $this->getDriveItem($path);

        if ($item === null || $item->type() !== DriveItem::TYPE_FILE) {
            throw UnableToReadFile::fromLocation($path, 'File not found');
        }

        return $this->getDriveItemContents($item)->detach();
    }

    public function delete(string $path): void
    {
        $item = $this->getDriveItem($path);

        if ($item === null || $item->type() !== DriveItem::TYPE_FILE || !$this->deleteDriveItem($item)) {
            throw UnableToDeleteFile::atLocation($path);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $item = $this->getDriveItem($path);

        if ($item === null || $item->type() !== DriveItem::TYPE_FOLDER || !$this->deleteDriveItem($item)) {
            throw UnableToDeleteDirectory::atLocation($path);
        }
    }

    /**
     * Create a directory, including any missing parent directories.
     */
    public function createDirectory(string $path, Config $config): void
    {
        $exists = [];

        foreach (array_filter(explode('/', trim($path, '/')), 'strlen') as $directory) {
            $directoryPath = implode('/', array_merge($exists, [$directory]));

            if (!$this->directoryExists($directoryPath)) {
                try {
                    $this->createDriveItem($directoryPath, DriveItem::TYPE_FOLDER);
                } catch (GraphAPIException | RuntimeException $e) {
                    throw UnableToCreateDirectory::atLocation($directoryPath, $e->getMessage(), $e);
                }
            }

            $exists[] = $directory;
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Visibility is not supported');
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->attributes($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->attributes($path, FileAttributes::ATTRIBUTE_MIME_TYPE);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->attributes($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->attributes($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $items = $deep
            ? $this->listDriveItemsRecursive($path)
            : $this->listDriveItems($path);

        foreach ($items as $item) {
            yield $this->normalizeDriveItem($item);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $item = $this->getDriveItem($source);

        if ($item === null) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }

        $newparent = dirname($destination);
        $newparent = $newparent === '.' ? '' : $newparent;

        try {
            $this->createDirectory($newparent, $config);
        } catch (UnableToCreateDirectory $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }

        $parentItem = $this->getDriveItem($newparent);

        $moved = $parentItem !== null && $this->updateDriveItem($item, [
            'name' => basename($destination),
            'parentReference' => [
                'id' => $parentItem->id(),
            ],
        ]);

        if (!$moved) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw UnableToCopyFile::fromLocationTo($source, $destination);
    }

    /**
     * Called by Illuminate\Filesystem\FilesystemAdapter::url()
     *
     * @param string $path
     * @return string|bool
     */
    public function getUrl($path)
    {
        try {
            $url = $this->getDriveItem($path);

            if ($url === null) {
                return false;
            }

            return $this->getDriveItemUrl($url);
        } catch (GraphAPIException $e) {
            return false;
        }
    }
}
