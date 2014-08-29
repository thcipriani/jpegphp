<?php

require_once(__DIR__ . '/Jpeg.php');

class JpegTest extends PHPUnit_Framework_Testcase
{
  public $jpeg;

  public function setup()
  {
    $this->jpeg = new Jpeg(__DIR__ . '/sik.jpg');
  }

  public function testShouldBeAValidJpegFile()
  {
    $this->assertTrue($this->jpeg->isFile(), 'Should be a valid file');
    $this->assertTrue($this->jpeg->isJpeg(), 'Should be a valid jpeg');
  }

  public function testItShouldHaveProperties()
  {
    $this->assertTrue($this->jpeg->getTileSize() > 0, 'Should have a tile size set');
  }

  public function testItShouldHaveAThreshold()
  {
    $this->assertTrue($this->jpeg->getThreshold() > 0, 'Should find a threshold to determine saliency');
  }

  public function testItShouldCompress()
  {
    $path = $this->jpeg->optimize();
    $this->assertTrue(strlen($path) > 0, 'Should return a path');
    echo "$path\n";
  }
}
