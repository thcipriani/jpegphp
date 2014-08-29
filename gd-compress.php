#!/usr/bin/env php
<?php

$compress_image = function($src, $dest , $quality) {
  $info = getimagesize($src);

  if ($info['mime'] == 'image/jpeg') {
    $image = imagecreatefromjpeg($src);
  } else {
    die("Unknown image file format\n");
  }

  //compress and save file to jpg
  imagejpeg($image, $dest, $quality);

  //return destination file
  return $dest;
};

if (! isset($argv[1]) || ! isset($argv[2]))
  die("Not enough arguments\n");

$base = __DIR__ . '/';

$filename    = $base . $argv[1];
$outfile     = $base . $argv[2];
$compression = $argv[3];

$finfo = new finfo(FILEINFO_MIME);
$mime_type = $finfo->file($filename);
$mime_info = explode(';', $mime_type);

$compress_image($filename, $outfile, $compression);
