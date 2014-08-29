<?php

class Jpeg {
  const SALIENT_FILE_NAME = 'salient.jpg';
  const TILE_SIZE = 32;
  const MAX_COMPRESSION = 90;
  const MIN_SALIENCE = 0.90;

  public $img;
  public $height;
  public $width;
  public $rows;
  public $cols;
  public $tileSize;
  public $threshold;
  protected $_isValid = false;
  protected $_path;
  protected $_salient;

  public function __construct($img = false)
  {
    if ($img)
      $this->setImage($img);
  }

  public function setImage($img)
  {
    $this->img = $img;

    if (! $this->validateImg())
      throw new Exception('Cannot call Jpeg on a non-jpeg image');
  }

  public function isFile()
  {
    if (! is_file($this->img))
      return false;

    return true;
  }

  public function isJpeg()
  {
    $info = getimagesize($this->img);
    if ($info['mime'] !== 'image/jpeg')
      return false;

    return true;
  }

  protected function validateImg()
  {
    if ($this->_isValid)
      return true;

    if (! isset($this->img) || ! $this->isFile() || ! $this->isJpeg())
      return false;

    $this->_isValid = true;
    return true;
  }

  public function getDims()
  {
    if (isset($this->width) && isset($this->height))
      return;

    $this->height = `identify -format %h {$this->img}`;
    $this->width = `identify -format %w {$this->img}`;
  }

  public function getCols()
  {
    $this->getDims();

    if (isset($this->rows) && isset($this->cols))
      return;

    $rCols = floor($this->width / self::TILE_SIZE);
    $rRows = floor($this->height / self::TILE_SIZE);

    $cols = $rCols * self::TILE_SIZE < $this->width
      ? $rCols + 1 : $rCols;

    $rows = $rRows * self::TILE_SIZE < $this->height
      ? $rRows + 1 : $rRows;

    $this->rows = $rows;
    $this->cols = $cols;
  }

  public function findTileSize()
  {
    if (! $this->width || ! $this->height)
      $this->getDims();

    $smallestDim = $this->width < $this->height
      ? $this->width
      : $this->height;

    $this->tileSize = self::TILE_SIZE;

    if ( $smallestDim >= 1025 && $smallestDim <= 2560 ) {
      $this->tileSize = 128;
    } else if ( $smallestDim >= 2561 ) {
      $this->tileSize = 256;
    }
  }

  public function findThreshold()
  {
    if (isset($this->threshold))
      return;

    $upper = 100;
    $lower = 0;
    $redo = false;

    $getMeanGray = function($opts) {
      return `SaliencyDetector -q -L{$opts['lower']} -U{$opts['upper']} "{$this->img}" "png:-" | identify -channel Gray -format "%[fx:255*mean]" -`;
    };

    $meanGray = $getMeanGray([
      'lower' => $lower,
      'upper' => $upper,
    ]);

    if ($meanGray > 40)
      $upper -= 50;

    if ($meanGray < 20)
      $lower = 50;

    $this->threshold = ($upper - $lower)/ 2 + $lower;
  }

  protected function makePath()
  {
    $hash = md5(microtime());
    $this->_path = '/tmp/' . implode('/', str_split(substr($hash, 0, 5)));

    if (! is_file($this->_path))
      if (! mkdir($this->_path, 0700, true))
        throw new Exception('Cannot create path for temp jpeg storage');

    return $this->_path;
  }

  public function makeStorage()
  {
    if (! isset($this->_path))
      $this->makePath();

    return $this->_path;
  }

  public function makeSalient()
  {
    $this->validateImg();
    $this->_salient = $this->makeStorage() . '/' . self::SALIENT_FILE_NAME;
    $this->findThreshold();
    `SaliencyDetector -q -L0 -U{$this->threshold} "{$this->img}" {$this->_salient}`;
  }

  public function makeSlices()
  {
    $tileSize = self::TILE_SIZE;
    `convert "{$this->img}" -crop "{$tileSize}"x"{$tileSize}" +repage +adjoin "{$this->makeStorage()}/tile-%06d.jpg"`;
  }

  public function compress()
  {
    $this->getDims();
    $this->getCols();

    $tileSize = self::TILE_SIZE;
    $cols = $this->cols;
    $rows = $this->rows;

    $count = 0;
    $countn = 0;

    // Loop through all the tiles
    for($x = 0; $x < $cols; $x++) {
      for($y = 0; $y < $rows; $y++) {
        $xOffset = $x * $tileSize;
        $yOffset = $y * $tileSize;

        // Find the mean saliency of the tile
        $mean = `identify -size "{$this->width}"x"{$this->height}" -channel Gray -format "%[fx:255*mean]" "{$this->_salient}[{$tileSize}x{$tileSize}+{$xOffset}+{$yOffset}]"`;

        $current = sprintf('tile-%06d', $count);
        // If it's not important, compress the shit out of it
        if ($mean < self::MIN_SALIENCE) {
          $file = "{$this->makeStorage()}/{$current}.jpg";
          imagejpeg(imagecreatefromjpeg($file), $file, self::MAX_COMPRESSION);
        }
        $count++;
      }
    }

  }

  public function reassemble()
  {
    $cols = $this->cols;
    $rows = $this->rows;
    $files = "$(find \"{$this->makeStorage()}\" -name \"tile-*.jpg\" | sort)";
    $out = __DIR__ . '/out.jpg';

    `montage -mode concatenate -tile "{$cols}x{$rows}" -- {$files} {$out}`;
  }

  public function optimize()
  {
    $this->makeSalient();
    $this->makeSlices();
    $this->compress();
    $this->reassemble();
  }
}
