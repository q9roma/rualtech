<?php
namespace App\Support;

/** Versioned 64-bit DCT perceptual hash. Similarity is a review hint, never identity proof. */
class PhotoPerceptualHash
{
    public const VERSION = 'phash-dct32-v1';
    public static function fromFile(string $path): ?string
    {
        $size=@getimagesize($path);
        if (!$size || $size[0]*$size[1]>40_000_000 || filesize($path)>15_000_000) return null;
        $source=@imagecreatefromstring(file_get_contents($path));
        if (!$source) return null;
        $small=imagecreatetruecolor(32,32);
        imagefill($small,0,0,imagecolorallocate($small,255,255,255));
        imagealphablending($small,true);
        imagecopyresampled($small,$source,0,0,0,0,32,32,imagesx($source),imagesy($source));
        imagedestroy($source);
        $pixels=[];
        for($y=0;$y<32;$y++) for($x=0;$x<32;$x++) {
            $rgb=imagecolorat($small,$x,$y);
            $pixels[$y][$x]=0.299*(($rgb>>16)&255)+0.587*(($rgb>>8)&255)+0.114*($rgb&255);
        }
        imagedestroy($small);
        static $cosines=null;
        if ($cosines===null) for($u=0;$u<8;$u++) for($x=0;$x<32;$x++) $cosines[$u][$x]=cos((2*$x+1)*$u*M_PI/64);
        $rows=[];$coefficients=[];
        for($y=0;$y<32;$y++) for($u=0;$u<8;$u++) {
            $sum=0;for($x=0;$x<32;$x++) $sum+=$pixels[$y][$x]*$cosines[$u][$x];
            $rows[$y][$u]=$sum;
        }
        for($v=0;$v<8;$v++) for($u=0;$u<8;$u++) {
            $sum=0;for($y=0;$y<32;$y++) $sum+=$rows[$y][$u]*$cosines[$v][$y];
            $coefficients[]=$sum;
        }
        $sorted=array_slice($coefficients,1);sort($sorted,SORT_NUMERIC);$median=$sorted[31];
        $bits='0';foreach(array_slice($coefficients,1) as $value) $bits.=($value>$median+0.000001 ? '1' : '0');
        $hash='';foreach(str_split($bits,4) as $nibble) $hash.=dechex(bindec($nibble));
        return $hash;
    }
    public static function distance(string $left,string $right): int
    {
        if (!preg_match('/\A[0-9a-f]{16}\z/',$left) || !preg_match('/\A[0-9a-f]{16}\z/',$right)) return 64;
        $counts=[0,1,1,2,1,2,2,3,1,2,2,3,2,3,3,4];$distance=0;
        for($i=0;$i<16;$i++) $distance+=$counts[hexdec($left[$i])^hexdec($right[$i])];
        return $distance;
    }
}
