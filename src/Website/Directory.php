<?php

/*
 * This file is part of the hyn/multi-tenant package.
 *
 * (c) Daniël Klabbers <daniel@klabbers.email>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see https://tenancy.dev
 * @see https://github.com/hyn/multi-tenant
 */

namespace Hyn\Tenancy\Website;

use Hyn\Tenancy\Contracts\Tenant;
use Hyn\Tenancy\Contracts\Website;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\Filesystem as LocalSystem;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

class Directory implements Filesystem
{
    use Macroable;
    /**
     * @var array
     */
    protected $folders;
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var Website
     */
    protected $website;
    /**
     * @var LocalSystem
     */
    protected $local;

    public function __construct(Filesystem $filesystem, Repository $config, LocalSystem $local)
    {
        $this->filesystem = $filesystem;
        $this->folders = $config->get('tenancy.folders', []);
        $this->local = $local;
    }

    /**
     * {@inheritdoc}
     */
    public function path($path = null)
    {
        $prefix = "{$this->getWebsite()->uuid}/";

        if ($path === null) {
            $path = '';
        }

        if (!Str::startsWith($path, $prefix)) {
            $path = "$prefix$path";
        }

        if ($this->isLocal()) {
            $config = $this->filesystem->getConfig();
            $path = sprintf(
                "%s/%s",
                $config['root'],
                $path
            );
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($path = null): bool
    {
        return $this->getWebsite() && $this->filesystem->exists($this->path($path));
    }

    /**
     * {@inheritdoc}
     */
    public function get($path)
    {
        return $this->filesystem->get(
            $this->path($path)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        return $this->filesystem->readStream($this->path($path));
    }

    /**
     * {@inheritdoc}
     */
    public function put($path, $contents, $options = [])
    {
        return $this->filesystem->put(
            $this->path($path),
            $contents,
            $options
        );
    }

    /**
     * {@inheritdoc}
     */
    public function putFile($path, $file = null, $options = [])
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function putFileAs($path, $file, $name = null, $options = [])
    {
        throw new RuntimeException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, array $options = [])
    {
        return $this->filesystem->writeStream($this->path($path), $resource, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        return $this->filesystem->getVisibility(
            $this->path($path)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        $this->filesystem->setVisibility(
            $this->path($path),
            $visibility
        );
    }

    /**
     * {@inheritdoc}
     */
    public function prepend($path, $data)
    {
        return $this->filesystem->prepend(
            $this->path($path),
            $data
        );
    }

    /**
     * {@inheritdoc}
     */
    public function append($path, $data)
    {
        return $this->filesystem->append(
            $this->path($path),
            $data
        );
    }

    /**
     * {@inheritdoc}
     */
    public function delete($paths)
    {
        return $this->filesystem->delete(
            collect((array)$paths)
                ->map(function ($path) {
                    return $this->path($path);
                })
                ->values()
                ->all()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function copy($from, $to)
    {
        return $this->filesystem->copy(
            $this->path($from),
            $this->path($to)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function move($from, $to)
    {
        return $this->filesystem->move(
            $this->path($from),
            $this->path($to)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function size($path)
    {
        return $this->filesystem->size(
            $this->path($path)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified($path)
    {
        return $this->filesystem->lastModified(
            $this->path($path)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function files($directory = null, $recursive = false)
    {
        return $this->filesystem->files(
            $this->path($directory),
            $recursive
        );
    }

    /**
     * {@inheritdoc}
     */
    public function allFiles($directory = null)
    {
        return $this->filesystem->allFiles(
            $this->path($directory)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function directories($directory = null, $recursive = false)
    {
        return $this->filesystem->directories(
            $this->path($directory),
            $recursive
        );
    }

    /**
     * {@inheritdoc}
     */
    public function allDirectories($directory = null)
    {
        return $this->filesystem->allDirectories(
            $this->path($directory)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function makeDirectory($path)
    {
        return $this->filesystem->makeDirectory(
            $this->path($path)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDirectory($directory)
    {
        return $this->filesystem->deleteDirectory(
            $this->path($directory)
        );
    }

    public function setWebsite(Website $website): Directory
    {
        $this->website = $website;

        return $this;
    }

    public function getWebsite(): Website|null
    {
        return $this->website ?? app(Tenant::class);
    }

    public function isLocal(): bool
    {
        return $this->filesystem->getAdapter() instanceof LocalFilesystemAdapter;
    }

    public function __call($method, $parameters)
    {
        if ($this->isLocal() && method_exists($this->local, $method)) {
            $parameters[0] = $this->path($parameters[0]);

            return call_user_func_array([$this->local, $method], $parameters);
        }
    }
}
