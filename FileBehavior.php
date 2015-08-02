<?php
/**
 * @author Chukancev Nikita <tpxtrime@mail.ru>
 */
namespace tpmanc\filebehavior;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use tpmanc\imagick\Imagick;
use yii\db\BaseActiveRecord;
use yii\web\UploadedFile;

/**
 * File behavior
 */
class FileBehavior extends Behavior
{
    public $fileModel;
    public $linkItemColumn = 'itemId';
    public $linkFileColumn = 'fileId';
    public $fileFolder;
    public $fileVar;
    public $imageSizes = false;

    private $file;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->fileModel === null) {
            throw new InvalidConfigException('The "fileModel" property must be set.');
        }
        if ($this->fileFolder === null) {
            throw new InvalidConfigException('The "fileFolder" property must be set.');
        }
        if ($this->fileVar === null) {
            throw new InvalidConfigException('The "fileVar" property must be set.');
        }
    }

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            BaseActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
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

    /**
     * Return full path to uplaod folder
     * @return string Path to upload folder
     */
    private function getFolderPath()
    {
        return Yii::getAlias($this->fileFolder) . '/';
    }

    // TODO: create exceptions
    /**
     * Save file to disk
     */
    private function saveFile($eventName)
    {
        $file = $this->owner->{$this->fileVar};
        $error = false;
        if ($file !== null && $file !== '') {
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

            if ($error) {
                foreach ($successDownloaded as $path) {
                    unlink($path);
                }
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }
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

    /**
     * This method is called after models save.
     */
    public function afterSave()
    {
        $this->saveFile($event);
    }
}
