<?php

require_once(__DIR__ . '/Jpeg.php');

class JpegTest extends PHPUnit_Framework_Testcase
{
  public $jpeg;

  public function testItWorks()
  {
    $this->jpeg = new Jpeg(__DIR__ . '/sik.jpg');
    $this->jpeg->optimize();
  // }

  // public function testShouldBeAValidJpegFile()
  // {
  //   $this->assertTrue($this->jpeg->isFile(), 'Should be a valid file');
  //   $this->assertTrue($this->jpeg->isJpeg(), 'Should be a valid jpeg');
  // }

  // public function testItShouldHaveDimensions()
  // {
  //   $this->jpeg->findTileSize();

  //   $this->assertTrue($this->jpeg->width > 0, 'Should have a width');
  //   $this->assertTrue($this->jpeg->height > 0, 'Should have a height');
  //   $this->assertTrue($this->jpeg->tileSize > 0, 'Should have a tile size set');
  // }

  // public function testItShouldHaveAThreshold()
  // {
  //   $this->jpeg->findThreshold();
  //   $this->assertTrue($this->jpeg->threshold > 0, 'Should find a threshold to determine saliency');
  }
}
