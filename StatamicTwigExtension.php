<?php

namespace Statamic\Addons\Twig;

use Statamic\API\Asset;
use Statamic\API\Config;
use Statamic\API\Content;
use Statamic\API\Entry;
use Statamic\API\File;
use Statamic\API\Image;
use Statamic\API\Page;
use Statamic\API\Str;
use Statamic\API\URL;
use Statamic\Contracts\Assets\Asset as AssetInterface;
use Statamic\Imaging\GlideImageManipulator;

/**
 * Extends Twig with features related to Statamic.
 */
class StatamicTwigExtension extends \Twig_Extension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_Function('get_asset', [$this, 'getAsset']),
            new \Twig_Function('get_entry', [$this, 'getEntry']),
            new \Twig_Function('get_page', [$this, 'getPage']),
            new \Twig_Function('get_content', [$this, 'getContent']),
            new \Twig_Function('collection', [$this, 'collection']),
            new \Twig_Function('glide', [$this, 'glide']),
            new \Twig_Function('theme', [$this, 'theme']),
            new \Twig_Function('env', [$this, 'env']),
        ];
    }

    /**
     * @param string $id Id or path to an asset.
     *
     * @return array
     */
    public function getAsset($id)
    {
        $asset = Asset::find($id);

        return ($asset) ? $asset->toArray() : [];
    }

    /**
     * @param string $id Id or url of an entry.
     *
     * @return array
     */
    public function getEntry($id)
    {
        $entry = Entry::find($id);

        if (!$entry) {
            $entry = Entry::whereUri($id);
        }

        return ($entry) ? $entry->toArray() : [];
    }

    /**
     * @param string $id Id or url of a page.
     *
     * @return array
     */
    public function getPage($id)
    {
        $page = Page::find($id);

        if (!$page) {
            $page = Page::whereUri($id);
        }

        return ($page) ? $page->toArray() : [];
    }

    /**
     * @param string $id Id or url of any content.
     *
     * @return array
     */
    public function getContent($id)
    {
        $content = Content::find($id);

        if (!$content) {
            $content = Content::whereUri($id);
        }

        return $content ? $content->toArray() : [];
    }

    /**
     * @param mixed $item
     * @param null $width
     * @param null $height
     * @param null $square
     * @param null $fit
     * @param null $crop
     * @param string $orient
     * @param int $quality
     * @param string $format
     * @param null $preset
     * @param bool $absolute
     *
     * @return string
     */
    public function glide($item, $width = null, $height = null, $square = null, $fit = null, $crop = null, $orient = 'auto', $quality = 90, $format = 'jpg', $preset = null, $absolute = false)
    {
        $asset = $this->normalizeGlideItem($item);

        /** @var GlideImageManipulator $manipulator */
        $manipulator = Image::manipulate($asset);

        $manipulator->orient($orient);
        $manipulator->quality($quality);
        $manipulator->format($format);

        if ($width !== null) $manipulator->width($width);
        if ($height !== null) $manipulator->height($height);
        if ($square !== null) $manipulator->square($square);
        if ($fit !== null) $manipulator->fit($fit);
        if ($crop !== null) $manipulator->crop($crop);
        if ($preset !== null) $manipulator->preset($preset);

        try {
            $url = $manipulator->build();
        } catch (\Exception $e) {
            return '';
        }

        return $absolute ? URL::makeAbsolute($url) : URL::makeRelative($url);
    }

    /**
     * @param string $file
     * @param bool $cache_bust
     * @param bool $absolute
     *
     * @return string
     */
    public function theme($file, $cache_bust = false, $absolute = true)
    {
        if (File::disk('theme')->exists($file)) {
            return '';
        }

        $url = URL::assemble(
            Config::get('system.filesystems.themes.url'),
            Config::get('theming.theme'),
            $file
        );

        $url = URL::prependSiteUrl(
            $url,
            Config::get('locale', default_locale()),
            false
        );

        if ($cache_bust) {
            $url .= '?v=' . File::disk('theme')->lastModified($file);
        }

        if (!$absolute) {
            $url = URL::makeRelative($url);
        }

        return $url;
    }

    /**
     * @param string $variable
     * @param null $default
     *
     * @return mixed
     */
    public function env($variable, $default = null)
    {
        return env($variable, $default);
    }

    /**
     * @param string|array $name
     *
     * @param bool $show_unpublished
     * @param bool $show_published
     * @param bool $show_future
     * @param bool $show_past
     * @param null $since
     * @param null $until
     * @param null $sort
     * @param null $limit
     * @param int $offset
     * @param null $locale
     * @param null $conditions
     *
     * @return array
     */
    public function collection($name, $show_unpublished = false, $show_published = true, $show_future = false, $show_past = true, $since = null, $until = null, $sort = null, $limit = null, $offset = 0, $locale = null, $conditions = null)
    {
        $collection = null;

        if ($name === '*') {
            $collection = Entry::all();
        } else if (is_string($name)) {
            // May be a single name or multiple names joined with "|".
            $collection = Entry::whereCollection(explode('|', $name));
        } else if (is_array($name)) {
            $collection = Entry::whereCollection($name);
        }

        if ($collection === null) {
            return [];
        }

        if ($locale) {
            $collection = $collection->localize($locale);
        }

        if (!$show_unpublished) {
            $collection = $collection->removeUnpublished();
        }

        if (!$show_published) {
            $collection = $collection->removePublished();
        }

        if (!$show_future) {
            $collection = $collection->removeFuture();
        }

        if (!$show_past) {
            $collection = $collection->removePast();
        }

        if ($since) {
            $collection = $collection->removeBefore($since);
        }

        if ($until) {
            $collection = $collection->removeAfter($until);
        }

        // Conditions are passed as string, e.g. title:contains=awesome&&author:is=joe
        if ($conditions) {
            $filters = [];
            $parts = explode('&&', $conditions);
            foreach ($parts as $part) {
                list($key, $value) = explode('=', $part);
                $filters[$key] = $value;
            }
            $collection = $collection->conditions($filters);
        }

        if ($sort) {
            $collection = $collection->multisort($sort)->values();
        }

        if ($offset || $limit) {
            $collection = $collection->splice($offset, $limit ?: $collection->count());
        }

        return $collection->entries()->toArray();
    }

    private function normalizeGlideItem($item)
    {
        if ($item instanceof AssetInterface) {
            return $item;
        }

        // External URLs are already fine as-is.
        if (Str::startsWith($item, ['http://', 'https://'])) {
            return $item;
        }

        // Double colons indicate an asset ID.
        if (Str::contains($item, '::')) {
            return Asset::find($item);
        }

        // In a subfolder installation, the subfolder will likely be passed in
        // with the path. We don't want it in there, so we'll strip it out.
        // We'll need it to have a leading slash to be treated as a URL.
        $item = Str::ensureLeft(Str::removeLeft($item, Config::getSiteUrl()), '/');

        // In order for auto focal cropping to happen, we need to provide an
        // actual asset instance to the manipulator instead of just a URL.
        return Asset::find($item);
    }
}
