<?php
/*
*   Поведение для добавления изображения к модели
*/
namespace tpmanc\fileBehavior;

use yii\base\Behavior;
use yii\web\UploadedFile;
use yii\base\Event;
use yii\db\ActiveRecord;
use app\components\Imagemagick;

class FileBehavior extends Behavior
{
    public $imageFolder = '';
    public $imageExtension = '';
    public $imageSizes = [
        'thumbWidth' => 100,
        'thumbHeight' => 100,
        'maxWidth' => 800,
        'maxHeight' => 800,
    ];

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    private function getImagePath()
    {
        return [
            'origin' => $this->getFolderPath() . $this->owner->imageId . '.' . $this->owner->imageExtension,
            'thumb' => $this->getFolderPath() . $this->owner->imageId . '_thumb.' . $this->owner->imageExtension,
        ];
    }

    public function getImageUrl()
    {
        return [
            'origin' => $this->getFolderUrl() . $this->owner->imageId . '.' . $this->owner->imageExtension,
            'thumb' => $this->getFolderUrl() . $this->owner->imageId . '_thumb.' . $this->owner->imageExtension,
        ];
    }

    private function getFolderPath()
    {
        return \Yii::getAlias('@webroot/upload/') . $this->owner->imageFolder . '/';
    }

    private function getFolderUrl()
    {
        return \Yii::getAlias('@web/upload/') . $this->owner->imageFolder . '/';
    }

    private function saveFile($eventName)
    {
        $file = $this->owner->file;
        $error = false;
        if( $file !== null ){
            if($eventName !== 'beforeUpdate' || $this->owner->imageId == 0){
                $image = new Images();
                $image->tooltip = '';
                if( !$image->save() ){
                    $error = true;
                }else{
                    $this->owner->imageId = $image->id;
                }
            }
            if( !$error ){
                $filePath = $this->getFolderPath() . $this->owner->imageId . '.' .$this->owner->imageExtension;
                $fileThumbPath = $this->getFolderPath() . $this->owner->imageId . '_thumb.' .$this->owner->imageExtension;
                if ( $file->saveAs($filePath ) ){
                    // resize
                    $image = new Imagemagick($filePath);
                    // $image->resizeImage( $filePath, $this->owner->imageSizes['maxWidth'], $this->owner->imageSizes['maxHeight'] );

                    // thumb
                    $image = new Imagemagick($filePath);
                    $image->makeThumb($fileThumbPath, $this->owner->imageSizes['thumbWidth'], $this->owner->imageSizes['thumbHeight']);
                }
            }
        }
    }

    private function deleteFile()
    {
        $id = $this->owner->imageId;
        $image = Images::findOne($id);
        if( $image !== null ){
            if( $image->delete() ){
                unlink( $this->getImagePath()['origin'] );
                unlink( $this->getImagePath()['thumb'] );
            }
        }
    }


    /* events */
    public function beforeSave($event)
    {
        $this->saveFile($event->name);
    }

    public function beforeDelete($event)
    {
        $this->deleteFile();
    }
}
