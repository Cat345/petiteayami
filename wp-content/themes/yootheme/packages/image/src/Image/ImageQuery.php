<?php

namespace YOOtheme\Image;

class ImageQuery extends ImageResizable
{
    /**
     * @var array<array<int, mixed>>
     */
    protected array $query = [];

    protected ?int $modified = null;

    public function __construct(string $file)
    {
        parent::__construct($file);

        if ($this->type) {
            $this->modified = @filemtime($this->path) ?: null;
        }
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        $query = $this->getQuery();

        if (!$query) {
            return parent::__toString();
        }

        $query = join('&', $query);

        $cacheKey = hash(
            PHP_VERSION_ID < 80100 ? 'md5' : 'xxh32',
            $this->file . $query . $this->modified,
        );

        return "image:/{$this->file}?{$query}&cachekey={$cacheKey}";
    }

    /**
     * @return array<string, string>
     */
    protected function getQuery(): array
    {
        $query = [];

        foreach ($this->query as $name => $args) {
            if ($value = join(',', $args)) {
                $query[$name] = $name . '=' . $value;
            }
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function type(string $type, int $quality = 80): self
    {
        $image = parent::type($type, $quality);

        $image->query['type'] = [$type, $quality];

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function crop($width = null, $height = null, $x = 'center', $y = 'center'): self
    {
        $image = parent::crop($width, $height, $x, $y);

        $image->query['crop'] = [$image->getWidth(), $image->getHeight(), $x, $y];

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function resize($width = null, $height = null, string $background = 'crop'): self
    {
        $image = parent::resize($width, $height, $background);

        $image->query['resize'] = [$image->getWidth(), $image->getHeight(), $background];

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function rotate(int $angle, string $background = 'transparent'): self
    {
        $image = parent::rotate($angle, $background);

        $image->query['rotate'] = [$angle, $background];

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function flip(bool $horizontal, bool $vertical): self
    {
        $image = parent::flip($horizontal, $vertical);

        $image->query['flip'] = [$horizontal, $vertical];

        return $image;
    }

    /**
     * @inheritdoc
     */
    public function thumbnail(
        $width = null,
        $height = null,
        bool $flip = false,
        string $x = 'center',
        string $y = 'center'
    ): self {
        $args = func_get_args();

        $query = $this->query;
        $image = parent::thumbnail($width, $height, $flip, $x, $y);
        $image->query = $query;

        $image->query['thumbnail'] = [
            $image->getWidth(),
            $image->getHeight(),
            ...array_slice($args, 2),
        ];

        return $image;
    }
}
