<?php namespace Overtrue\Wechat\Messages;

use Overtrue\Wechat\Media;
use Overtrue\Wechat\Utils\XML;

class Image extends AbstractMessage implements MessageInterface {

    protected $properties = array('media_id');

    /**
     * 设置图片
     *
     * @param string $path
     *
     * @return Overtrue\Wechat\Messages\Image
     */
    public function image($path)
    {
        $this->attributes['media_id'] = Media::image($path);

        error_log($this->attributes['media_id']);

        return $this;
    }

    public function formatToClient()
    {
        return array(
                'touser'  => $this->to,
                'msgtype' => 'image',
                'image'   => array(
                              'media_id' => $this->media_id
                             ),
              );
    }

    public function formatToServer()
    {
        $response = array(
                     'ToUserName'   => $this->to,
                     'FromUserName' => $this->from,
                     'CreateTime'   => time(),
                     'MsgType'      => 'image',
                     'Image'        => array(
                                        'MediaId' => $this->media_id,
                                       ),
                    ));

        return XML::build($response);
    }

}