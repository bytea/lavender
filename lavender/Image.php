<?php
namespace Lavender;

class Image
{
	protected $width;
	protected $height;
	protected $type;
	protected $file;
	protected $src_img_handle;

	/**
	 * 初始化图片对象
	 *
	 * @param string $file 图片文件路径
	 */
	public function __construct($file)
	{
		$info = getimagesize($file);
		if (!$info) {
			throw new Exception('file type invalid', -1001);
		}

		$this->file = $file;
		$this->width = $info[0];
		$this->height = $info[1];
		$this->type = $info[2];
	}

	/**
	 * 生成副本
	 *
	 * @param string $file 副本宽度
	 * @param int $width 副本宽度
	 * @param int $height 副本高度
	 * @param int $quality 副本质量0-100
	 * @param array $options {fill:boolean,enlarge:boolean,src_x:int,src_x:int,src_w:int,src_h:int}
	 *
	 * @return boolean
	 */
	public function save_copy($file, $width, $height, $quality=100, $options = array())
	{
		//if raw size small than dest size and not enlarge
		if (empty($options['enlarge']) && $this->width <= $width && $this->height <= $height) {
			$result = copy($this->file, $file);
			if (!$result) {
				throw new Exception("save image copy failed", Errno::IMAGE_SAVE_FAILED);
			}

			return array($this->width, $this->height);
		}

		if (!$this->src_img_handle) {
			switch ($this->type) {
				case 1:
					$this->src_img_handle = imagecreatefromgif($this->file);
					break;
				case 2:
					$this->src_img_handle = imagecreatefromjpeg($this->file);
					break;
				case 3:
					$this->src_img_handle = imagecreatefrompng($this->file);
					break;
				default:
					throw new Exception("image type ({$this->type}) not defined", Errno::IMAGE_TYPE_INVALID);
			}
		}

		$srcW = $this->width;
		$srcH = $this->height;

		$src_x = empty($options['src_x']) || $options['src_x'] > $this->width ? 0 : $options['src_x'];
		$src_y = empty($options['src_y']) || $options['src_y'] > $this->height ? 0 : $options['src_y'];

		$srcW -= $src_x;
		$srcH -= $src_y;

		$srcW = isset($options['src_w']) && $options['src_w'] < $this->width ? $options['src_w'] : $this->width;
		$srcH = isset($options['src_h']) && $options['src_h'] < $this->height ? $options['src_h'] : $this->height;

		$dstX = 0;
		$dstY = 0;

		if ($srcW * $height > $srcH * $width) {// srcW/srcH > dstW/dstH ，源比目标扁
			$dstRealW = $width;
			$dstRealH = round($srcH * $dstRealW / $srcW);
		} else {
			$dstRealH = $height;
			$dstRealW = round($srcW * $dstRealH / $srcH);
		}

		if (empty($options['fill'])) {
			$dst_img = imagecreatetruecolor($dstRealW, $dstRealH);
		}
		else {
			$dstX = floor(($width - $dstRealW) / 2);
			$dstY = floor(($height - $dstRealH) / 2);
			$dst_img = imagecreatetruecolor($width, $height);
			$back_color = imagecolorallocate($dst_img, 255, 255, 255);//缩图空出部分的背景色
			imagefilledrectangle($dst_img, 0, 0, $width, $height, $back_color);
		}

		//ImageCopyResized($dst_img, $this->src_img_handle, 0, 0, 0, 0, $dstRealW, $dstRealH, $srcW, $srcH);
		imagecopyresampled($dst_img, $this->src_img_handle, $dstX, $dstY, $src_x, $src_y, $dstRealW, $dstRealH, $srcW, $srcH);

		switch ($this->type) {
			case 1:
				$result = imagegif($dst_img, $file);
				break;
			case 2:
				$result = imagejpeg($dst_img, $file, $quality);
				break;
			case 3:
				$result = imagepng($dst_img, $file);
				break;
		}

		chmod($file, 0644);
		imagedestroy($dst_img);

		if (!$result) {
			throw new Exception("save image copy failed", Errno::IMAGE_SAVE_FAILED);
		}

		return array($dstRealW, $dstRealH);
	}

	/**
	 * 计算在保存指定尺寸后的实际尺寸
	 *
	 * @param int $width 副本宽度
	 * @param int $height 副本高度
	 * @param array $options [fill=>boolean,src_x=>int,src_x=>int,src_w=>int,src_h=>int]
	 *
	 * @return boolean
	 */
	public function get_real_size($width, $height, $options = array())
	{
		if ($this->width < $width && $this->height < $height) {
			return array('width' => $this->width, 'height' => $this->height);
		}

		$srcW = $this->width;
		$srcH = $this->height;

		$src_x = empty($options['src_x']) || $options['src_x'] > $this->width ? 0 : $options['src_x'];
		$src_y = empty($options['src_y']) || $options['src_y'] > $this->height ? 0 : $options['src_y'];

		$srcW -= $src_x;
		$srcH -= $src_y;

		$srcW = isset($options['src_w']) && $options['src_w'] < $this->width ? $options['src_w'] : $this->width;
		$srcH = isset($options['src_h']) && $options['src_h'] < $this->height ? $options['src_h'] : $this->height;

		$dstX = 0;
		$dstY = 0;

		if ($srcW * $height > $srcH * $width) {// srcW/srcH > dstW/dstH ，源比目标扁
			$dstRealW = $width;
			$dstRealH = round($srcH * $dstRealW / $srcW);
		} else {
			$dstRealH = $height;
			$dstRealW = round($srcW * $dstRealH / $srcH);
		}

		return array('width' => $dstRealW, 'height' => $dstRealH);
	}

	public function __destruct()
	{
		if ($this->src_img_handle) {
			imagedestroy($this->src_img_handle);
		}
	}
}