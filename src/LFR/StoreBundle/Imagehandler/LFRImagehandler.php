<?php

namespace LFR\StoreBundle\Imagehandler;

class LFRImagehandler {
  public function __construct($img_dir)
  {
      $this->img_dir = $img_dir;
  }
  public function check_image_small_copies($path){
      $path_no_ext = preg_replace('/\\.[^.\\s]{3,4}$/', '', $path);
      list($width, $height) = getimagesize($path);
      if(!file_exists($path_no_ext.'-sm.jpeg')){
        $w = 500;
        $h = $height * $w / $width;
        $src = imagecreatefromjpeg($path);
        $image_small = imagecreatetruecolor($w, $h);
        imagecopyresampled($image_small, $src, 0, 0, 0, 0, $w, $h, $width, $height);
        $path_no_ext = preg_replace('/\\.[^.\\s]{3,4}$/', '', $path);
        imagejpeg($image_small, $path_no_ext.'-sm.jpeg');
      }
      return true;
  }
  public function image_fix_orientation($path)
  {
      $image = imagecreatefromjpeg($path);
      $exif = exif_read_data($path);

      if (empty($exif['Orientation']))
      {
          return false;
      }
      switch ($exif['Orientation'])
      {
          case 3:
              $image = imagerotate($image, 180, 0);
              break;
          case 6:
              $image = imagerotate($image, - 90, 0);
              break;
          case 8:
              $image = imagerotate($image, 90, 0);
              break;
      }
      imagejpeg($image, $path);
      return true;
  }
  public function check_image($filename, $filename_image_small, $size){
      $filename = $this->img_dir.'/'.$filename;
      $filename_image_small = $this->img_dir.'/'.$filename_image_small;
      if(!file_exists($filename_image_small)){
        list($width, $height) = getimagesize($filename);
        switch ($size) {
            case 'xs':
                $w = 300;
                break;
            case 'sm':
                $w = 400;
                break;
            case 'md':
                $w = 600;
                break;
            default:
                $w = $width;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'jpg':
                $src = imagecreatefromjpeg($filename);
                break;
            case 'jpeg':
                $src = imagecreatefromjpeg($filename);
                break;
            case 'JPG':
                $src = imagecreatefromjpeg($filename);
                break;
            case 'png':
                $src = imagecreatefrompng($filename);
                break;
            case 'gif':
                $src = imagecreatefromgif($filename);
                break;
            default:
                var_dump('File extension '.$extension.' not known.');
        }
        $h = $height * $w / $width;
        $image_small = imagecreatetruecolor($w, $h);
        imagecopyresampled($image_small, $src, 0, 0, 0, 0, $w, $h, $width, $height);
        switch ($extension) {
            case 'jpg':
                imagejpeg($image_small, $filename_image_small);
                break;
            case 'jpeg':
                imagejpeg($image_small, $filename_image_small);
                break;
            case 'JPG':
                imagejpeg($image_small, $filename_image_small);
                break;
            case 'png':
                imagepng($image_small, $filename_image_small);
                break;
            case 'gif':
                imagegif($image_small, $filename_image_small);
                break;
        }
      }
      return true;
  }
  public function get_image_in_quality($filename, $size){
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $filename_no_ext = preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename);
    $filename_image_small = $filename_no_ext.'-'.$size.'.'.$extension;
    $this->check_image($filename, $filename_image_small, $size);
    return $filename_image_small;
  }
}
