<?php

namespace Microsoft\GraphAPI\Filesystem\Models;

use DateTimeInterface;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use Microsoft\GraphAPI\Facades\GraphAPI;

final class DriveItem
{
    const TYPE_FILE = 'file';
    const TYPE_FOLDER = 'folder';

    protected array $driveItem;

    protected static array $cache = [];

    protected function __construct(array $driveItem = null)
    {
        $this->driveItem = $driveItem;
    }

    public static function getByPath(string $path): DriveItem
    {
        $path = trim($path, '/');

        if (array_key_exists($path, DriveItem::$cache) && DriveItem::$cache[$path] instanceof DriveItem) {
            return DriveItem::$cache[$path];
        }

        $item = GraphAPI::get($path)->json();
        $driveItem = new DriveItem($item);

        DriveItem::$cache[$path] = $driveItem;

        return $driveItem;
    }

    /**
     * @param string $siteId
     * @param string $path
     * @return DriveItem[]
     */
    public static function listByPath(string $path = '/', array $query = []): array
    {
        if (array_key_exists($path, DriveItem::$cache) && is_array(DriveItem::$cache[$path])) {
            return DriveItem::$cache[$path];
        }

        $path = trim($path, '/');

        $query = array_filter($query);
        $path = sprintf('%s?%s', $path, http_build_query($query));
        $items = array_map(fn ($item) => new DriveItem($item), GraphAPI::get($path)->json()['value']);

        DriveItem::$cache[$path] = $items;

        return $items;
    }

    public function createdDateTime(): DateTimeInterface
    {
        return Carbon::parse($this->driveItem['createdDateTime']);
    }

    public function etag(): string
    {
        return $this->driveItem['eTag'];
    }

    public function id(): string
    {
        return $this->driveItem['id'];
    }

    public function lastModifiedDateTime(): DateTimeInterface
    {
        return Carbon::parse($this->driveItem['lastModifiedDateTime']);
    }

    public function name(): string
    {
        return $this->driveItem['name'];
    }

    public function url(): string
    {
        return $this->driveItem['webUrl'];
    }

    public function ctag(): int
    {
        return $this->driveItem['size'];
    }

    public function size(): int
    {
        return $this->driveItem['size'];
    }

    public function createdBy(): string
    {
        return $this->driveItem['createdBy']['user']['displayName'];
    }

    public function lastModifiedBy(): string
    {
        return $this->driveItem['lastModifiedBy']['user']['displayName'];
    }

    public function path(): string
    {
        $path = Str::after($this->driveItem['parentReference']['path'], ':');
        return sprintf('%s/%s', $path, $this->name());
    }

    public function type(): string
    {
        return array_key_exists(DriveItem::TYPE_FILE, $this->driveItem) ? DriveItem::TYPE_FILE : DriveItem::TYPE_FOLDER;
    }

    public function childCount(): int
    {
        if ($this->type() === DriveItem::TYPE_FOLDER) {
            return $this->driveItem['folder']['childCount'];
        }

        return 0;
    }

    public function mimeType(): ?string
    {
        if ($this->type() === DriveItem::TYPE_FOLDER) {
            return 'directory';
        }

        return $this->driveItem['file']['mimeType'];
    }
}
