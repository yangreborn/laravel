<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicReport extends Mailable
{
    use SerializesModels;

    public $subject;
    private $image_path;
    private $share_url;

    /**
     * Create a new message instance.
     *
     * @param $config
     *
     * @return void
     */
    public function __construct($config = [])
    {
        $this->image_path = $config['image_path'];
        $this->share_url = $config['share_url'];
        $this->subject = $config['subject'];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $images = $this->imageToPieces($this->image_path);
        return $this->view('emails.public.index', ['images' => $images, 'url' => $this->share_url]);
    }


    private function imageToPieces($image_path){
        ini_set('max_execution_time', 360);
        ini_set('memory_limit', '2048M');
        $image = file_get_contents($image_path);
        $image_resource = imagecreatefromstring($image);
        $image_info = getimagesizefromstring($image);
        $width = $image_info[0];
        $height = $image_info[1];
        $slice_start = 0;
        $step = round($height/27) > 100 ? round($height/27) : 100; // 图片裁切高度
        $pieces = [];

        while ($slice_start < $height){
            $crop_height = ($slice_start + $step) > $height ? ($height - $slice_start) : $step;
            $piece = imagecrop($image_resource, ['x' => 0, 'y' => $slice_start, 'width' => $width, 'height' => $crop_height]);
            if ($piece !== false){
                $file_name = Storage::path('attach/'.Str::random(40).'.png');
                imagePng($piece, $file_name);
                compress_png($file_name, $file_name);
                $pieces[] = file_get_contents($file_name);
                imagedestroy($piece);
            }
            $slice_start += $step;
        }
        imagedestroy($image_resource);

        return $pieces;
    }
}
