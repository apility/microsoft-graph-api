<?php

namespace Microsoft\GraphAPI\Filesystem;

use RuntimeException;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Config;

use Microsoft\GraphAPI\Exceptions\GraphAPIException;
use Microsoft\GraphAPI\Facades\GraphAPI;
use Microsoft\GraphAPI\Filesystem\Models\DriveItem;

use Psr\Http\Message\StreamInterface;

class Adapter extends AbstractAdapter
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

    protected function normalizeDriveItem(DriveItem $item)
    {
        $normalized = [
            'type' => $item->type() === DriveItem::TYPE_FILE ? 'file' : 'dir',
            'path' => $item->path(),
            'timestamp' => $item->lastModifiedDateTime()->getTimestamp(),
            'size' => $item->size(),
            'mimetype' => $item->mimeType(),
            'visibility' => 'public',
            'dirname' => $this->normalizeDirname($item->path()),
        ];

        return $normalized;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function write($path, $contents, Config $config)
    {
        return false;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function writeStream($path, $resource, Config $config)
    {
        return false;
    }

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function update($path, $contents, Config $config)
    {
        return false;
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return array|false false on failure file meta data on success
     */
    public function updateStream($path, $resource, Config $config)
    {
        return false;
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function rename($path, $newpath)
    {
        $item = $this->getDriveItem($path);

        if ($item === null) {
            return false;
        }

        $newname = basename($newpath);
        $newparent = dirname($newpath);
        $newparent = $newparent === '.' ? '' : $newparent;

        $path = explode('/', $newparent);
        $exists = [];

        while (count($path) > 0) {
            $directory = array_shift($path);
            $directoryPath = implode('/', array_merge($exists, [$directory]));

            if (!$this->has($directoryPath)) {
                $this->createDir($directoryPath, new Config());
            }

            $exists[] = $directory;
        }

        $parentItem = $this->getDriveItem($newparent);

        if ($parentItem === null) {
            return false;
        }

        return $this->updateDriveItem($item, [
            'name' => $newname,
            'parentReference' => [
                'id' => $parentItem->id(),
            ],
        ]);
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    public function copy($path, $newpath)
    {
        return false;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    public function delete($path)
    {
        $directory = $this->getDriveItem($path);

        if ($directory === null) {
            return false;
        }

        if ($directory->type() !== DriveItem::TYPE_FILE) {
            return false;
        }

        return $this->deleteDriveItem($directory);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return bool
     */
    public function deleteDir($dirname)
    {
        $directory = $this->getDriveItem($dirname);

        if ($directory === null) {
            return false;
        }

        if ($directory->type() !== DriveItem::TYPE_FOLDER) {
            return false;
        }

        return $this->deleteDriveItem($directory);
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return array|false
     */
    public function createDir($dirname, Config $config)
    {
        try {
            return $this->normalizeDriveItem(
                $this->createDriveItem($dirname, DriveItem::TYPE_FOLDER)
            );
        } catch (GraphAPIException $e) {
            return false;
        }
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return false file meta data
     */
    public function setVisibility($path, $visibility)
    {
        return false;
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    public function has($path)
    {
        return $this->getDriveItem($path) !== null;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $item = $this->getDriveItem($path);

        if ($item === null) {
            return false;
        }

        if ($item === null || $item->type() !== DriveItem::TYPE_FILE) {
            return false;
        }

        $contents = (string) $this->getDriveItemContents($item);

        return ['contents' => $contents];
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        $item = $this->getDriveItem($path);

        if ($item === null || $item->type() !== DriveItem::TYPE_FILE) {
            return false;
        }

        $stream = $this->getDriveItemContents($item)->detach();

        return ['type' => 'file', 'path' => $path, 'stream' => $stream];
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $contents = $recursive
            ? $this->listDriveItemsRecursive($directory)
            : $this->listDriveItems($directory);

        return array_map(fn (DriveItem $item) => $this->normalizeDriveItem($item), $contents);
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        return $this->normalizeDriveItem(
            $this->getDriveItem($path)
        );
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the last modified time of a file as a timestamp.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getVisibility($path)
    {
        return $this->getMetadata($path);
    }
}
