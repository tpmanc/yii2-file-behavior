<?php
/*
*   Поведение для добавления изображения к модели
*/
namespace tpmanc\filebehavior;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\imagine\Image;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;

class FileBehavior extends Behavior
{
    public $fileModel;
    public $fileLinkModel;
    public $linkItemColumn = 'itemId';
    public $linkFileColumn = 'fileId';
    public $fileFolder = '';
    public $fileVar;
    public $fileType;
    public $imageSizes = false;

    private $file;

    /**
     * @inheritdoc
     */
    // public function init()
    // {
    //     parent::init();
    //     if ($this->attribute === null) {
    //         throw new InvalidConfigException('The "attribute" property must be set.');
    //     }
    //     if ($this->path === null) {
    //         throw new InvalidConfigException('The "path" property must be set.');
    //     }
    //     if ($this->url === null) {
    //         throw new InvalidConfigException('The "url" property must be set.');
    //     }
    // }

    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            BaseActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            BaseActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            BaseActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            BaseActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    // private function getImagePath()
    // {
    //     return [
    //         'origin' => $this->getFolderPath() . $this->owner->imageId . '.' . $this->owner->imageExtension,
    //         'thumb' => $this->getFolderPath() . $this->owner->imageId . '_thumb.' . $this->owner->imageExtension,
    //     ];
    // }

    // public function getImageUrl()
    // {
    //     return [
    //         'origin' => $this->getFolderUrl() . $this->owner->imageId . '.' . $this->owner->imageExtension,
    //         'thumb' => $this->getFolderUrl() . $this->owner->imageId . '_thumb.' . $this->owner->imageExtension,
    //     ];
    // }

    private function getFolderPath()
    {
        return Yii::getAlias($this->fileFolder) . '/';
    }

    // private function getFolderUrl()
    // {
    //     return \Yii::getAlias('@web/upload/') . $this->owner->imageFolder . '/';
    // }

    private function saveFile($eventName)
    {
        $file = $this->owner->{$this->fileVar};
        $error = false;
        if ($file !== null) {
            $fileModelClass = $this->fileModel;
            $transaction = $fileModelClass::getDb()->beginTransaction();
            // save file model
            $fileModel = new $fileModelClass;
            $fileModel->image = 'temp';
            if ($fileModel->save()) {
                $fileName = $fileModel->id . '.' . $file->extension;
                $fileModel->image = $fileName;
                if (!$fileModel->save()) {
                    $error = true;
                }
            } else {
                $error = true;
            }
            if (!$error) {
                // save file link model
                $linkModel = new $this->fileLinkModel;
                $linkModel->{$this->linkFileColumn} = $fileModel->id;
                $linkModel->{$this->linkItemColumn} = $this->owner->id;
                if (!$linkModel->save()) {
                    $error = true;
                }
            }
            if (!$error) {
                // save files
                if ($this->imageSizes !== false && is_array($this->imageSizes)) {
                    foreach ($this->imageSizes as $sizeName => $size) {
                        if (isset($size['folder']) && $size['folder'] !== '') {
                            $filePath = $this->getFolderPath() . $size['folder'] . '/' . $fileName;
                        } else {
                            $filePath = $this->getFolderPath() . '/' . $fileName;
                        }
                        if (!$file->saveAs($filePath)) {
                            $error = true;
                            break;
                        } else {
                            if (isset($size['width']) && isset($size['height'])) {
                                Image::thumbnail($filePath, $size['width'], $size['height'])
                                    ->save($filePath, ['quality' => 100]);
                            }
                        }
                    }
                } else {
                    $filePath = $this->getFolderPath() . $fileName;
                    if (!$file->saveAs($filePath)) {
                        $error = true;
                    }
                }
            }
        }
        if ($error) {
            $transaction->rollBack();
        } else {
            $transaction->commit();
        }
    }

    // private function deleteFile()
    // {
    //     $id = $this->owner->imageId;
    //     $image = Images::findOne($id);
    //     if ($image !== null) {
    //         if ($image->delete()) {
    //             unlink( $this->getImagePath()['origin'] );
    //             unlink( $this->getImagePath()['thumb'] );
    //         }
    //     }
    // }

    /**
     * Invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $this->file = UploadedFile::getInstance($model, $this->fileVar);
        if ($this->file instanceof UploadedFile) {
            $model->{$this->fileVar} = $this->file;
        }
    }


    /* events */
    public function beforeSave($event)
    {
        // $this->saveFile($event->name);
    }

    public function afterSave()
    {
        $this->saveFile($event);
    }

    // public function beforeDelete($event)
    // {
    //     $this->deleteFile();
    // }
}
