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
    public $imageSizeModel;
    public $linkItemColumn = 'itemId';
    public $linkFileColumn = 'imageId';
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
        if ($this->imageSizes === null) {
            throw new InvalidConfigException('The "imageSizes" property must be set.');
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

    public function getImages($size = 'default', $count = false)
    {
        $fileModelClass = $this->fileModel;
        $imageSizeModel = $this->imageSizeModel;
        $imageTableName = $fileModelClass::tableName();
        $tableSizeName = $imageSizeModel::tableName();
        $sql = "SELECT
                    $imageTableName.*,
                    $tableSizeName.path,
                    $tableSizeName.size
                FROM
                    $imageTableName
                LEFT JOIN $tableSizeName ON $tableSizeName.imageId = $imageTableName.id
                WHERE
                    itemId = :itemid AND
                    size = :size";
        $images = $fileModelClass::findBySql($sql, [
            ':itemid' => $this->owner->id,
            ':size' => $size,
        ])->asArray()->all();
        return $images;
    }

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
                $imageId = $this->saveToDb($file);
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
                    $error = $this->addImageSize($imageId, $path, $sizeName);
                    $fileName = $imageId . '.' . $file->extension;
                    if ($error === false) {
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
                $imageId = $this->saveToDb($file);
                $error = $this->addImageSize($imageId, $path);
                if ($imageId !== false) {
                    $fileName = $imageId . '.' . $file->extension;
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
     * @param obejct $file
     * @return integer|boolean File id or false if error
     */
    private function saveToDb($file)
    {
        $fileModelClass = $this->fileModel;
        $fileModel = new $fileModelClass;
        $fileModel->itemId = $this->owner->id;
        $fileModel->name = 'temp';
        if ($fileModel->save()) {
            $fileName = $fileModel->id . '.' . $file->extension;
            $fileModel->name = $fileName;
            if ($fileModel->save()) {
                return $fileModel->id;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Add new image size
     * @param integer $imageId Image id
     * @param string $path Image path
     * @param string $size Size label
     * @return boolean Boolean result
     */
    private function addImageSize($imageId, $path, $size = 'default')
    {
        $imageSize = new $this->imageSizeModel;
        $imageSize->imageId = $imageId;
        $imageSize->path = str_replace('@upload', '', $path);
        $imageSize->size = $size;
        if ($imageSize->save()) {
            return false;
        } else {
            return true;
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
