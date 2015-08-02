<?php
/*
*   Поведение для добавления изображения к модели
*/
namespace tpmanc\filebehavior;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use tpmanc\imagick\Imagick;
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

    private function saveFile($eventName)
    {
        $file = $this->owner->{$this->fileVar};
        $error = false;
        if ($file !== null) {
            $fileModelClass = $this->fileModel;
            $transaction = $fileModelClass::getDb()->beginTransaction();

            $successDownloaded = [];
            // save files
            if ($this->imageSizes !== false && is_array($this->imageSizes)) {
                $sizeCount = count($this->imageSizes);
                $sizeNumber = 0;
                foreach ($this->imageSizes as $sizeName => $size) {
                    $sizeNumber++;
                    $deleteTempFile = false;
                    if ($sizeNumber == $sizeCount) {
                        $deleteTempFile = true;
                    }
                    if (isset($size['folder']) && $size['folder'] !== '') {
                        $path = $this->fileFolder . '/' . $size['folder'] . '/';
                    } else {
                        $path = $this->fileFolder . '/';
                    }
                    // save to DB
                    $fileName = $this->saveToDb($file, $path, $sizeName);
                    if ($fileName !== false) {
                        if (isset($size['folder']) && $size['folder'] !== '') {
                            $filePath = $this->getFolderPath() . $size['folder'] . '/' . $fileName;
                        } else {
                            $filePath = $this->getFolderPath() . '/' . $fileName;
                        }
                        if ($file->saveAs($filePath, $deleteTempFile)) {
                            $successDownloaded[] = $filePath;
                            if (isset($size['width']) && isset($size['height'])) {
                                Imagick::open($filePath)->resize($size['width'], $size['height'])->saveTo($filePath);
                            }
                        } else {
                            $error = true;
                            break;
                        }
                    } else {
                        $error = true;
                    }
                }
            } else {
                $path = $this->fileFolder . '/';
                $fileName = $this->saveToDb($file, $path);
                if ($fileName !== false) {
                    $filePath = $this->getFolderPath() . $fileName;
                    if (!$file->saveAs($filePath)) {
                        $error = true;
                    }
                } else {
                    $error = true;
                }
            }
        }
        if ($error) {
            foreach ($successDownloaded as $path) {
                unlink($path);
            }
            $transaction->rollBack();
        } else {
            $transaction->commit();
        }
    }

    /**
     * Save file model
     * @param object $file Active Record object
     * @param string $path Image path
     * @param string $size Size label
     * @return string|booleab File name or false if error
     */
    private function saveToDb($file, $path, $size = 'default')
    {
        $fileModelClass = $this->fileModel;
        $fileModel = new $fileModelClass;
        $fileModel->itemId = $this->owner->id;
        $fileModel->image = 'temp';
        $fileModel->path = $path;
        $fileModel->size = $size;

        if ($fileModel->save()) {
            $fileName = $fileModel->id . '.' . $file->extension;
            $fileModel->image = $fileName;
            if ($fileModel->save()) {
                return $fileName;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

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
