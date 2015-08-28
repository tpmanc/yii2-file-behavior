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
 * Image behavior
 */
class ImageBehavior extends Behavior
{
    public $imageModel;
    public $imageSizeModel;
    public $linkItemColumn = 'itemId';
    public $imageFolder;
    public $webImageFolder;
    public $imageVariable;
    public $imageSizes = false;
    public $noImagePath;

    private $file;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->imageModel === null) {
            throw new InvalidConfigException('The "imageModel" property must be set.');
        }
        if ($this->imageSizes === null) {
            throw new InvalidConfigException('The "imageSizes" property must be set.');
        }
        if ($this->imageFolder === null) {
            throw new InvalidConfigException('The "imageFolder" property must be set.');
        }
        if ($this->webImageFolder === null) {
            throw new InvalidConfigException('The "webImageFolder" property must be set.');
        }
        if ($this->imageVariable === null) {
            throw new InvalidConfigException('The "imageVariable" property must be set.');
        }
        if ($this->noImagePath === null) {
            throw new InvalidConfigException('The "noImagePath" property must be set.');
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

    /**
     * Get image array of item
     * @param integer $count Count of images
     * @param string $size Image size title
     * @return string Path to first item image
     */
    public function getImages($count = false, $size = 'default')
    {
        $imageModelClass = $this->imageModel;
        $imageSizeModel = $this->imageSizeModel;
        $imageTableName = $imageModelClass::tableName();
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
        if ($count !== false) {
            $sql .= " LIMIT $count";
        }
        $images = $imageModelClass::findBySql($sql, [
            ':itemid' => $this->owner->id,
            ':size' => $size,
        ])->asArray()->all();

        $result = [];
        if (empty($images)) {
            $result[] = Yii::getAlias($this->noImagePath);
        } else {
            foreach ($images as $image) {
                $result[] = Yii::getAlias($this->webImageFolder . $image['path'] . $image['name']);
            }
        }

        return $result;
    }

    /**
     * Get first image of item
     * @param string $size Image size title
     * @return string Path to first item image
     */
    public function getImage($size = 'default')
    {
        $imageModelClass = $this->imageModel;
        $imageSizeModel = $this->imageSizeModel;
        $imageTableName = $imageModelClass::tableName();
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
                    size = :size LIMIT 1";
        $image = $imageModelClass::findBySql($sql, [
            ':itemid' => $this->owner->id,
            ':size' => $size,
        ])->asArray()->one();

        if ($image === null) {
            return Yii::getAlias($this->noImagePath);
        } else {
            return Yii::getAlias($this->webImageFolder . $image['path'] . $image['name']);
        }
    }

    /**
     * Return full path to uplaod folder
     * @return string Path to upload folder
     */
    private function getFolderPath()
    {
        return Yii::getAlias($this->imageFolder) . '/';
    }

    // TODO: create exceptions
    /**
     * Save image to disk
     */
    private function saveImage()
    {
        $file = $this->owner->{$this->imageVariable};
        $error = false;
        if ($file !== null && $file !== '') {
            $imageModelClass = $this->imageModel;
            $transaction = $imageModelClass::getDb()->beginTransaction();

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
                        $path = $this->imageFolder . '/' . $size['folder'] . '/';
                    } else {
                        $path = $this->imageFolder . '/';
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
                $path = $this->imageFolder . '/';
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
     * Save image model
     * @param obejct $file
     * @return integer|boolean Image id or false if error
     */
    private function saveToDb($file)
    {
        $imageModelClass = $this->imageModel;
        $imageModel = new $imageModelClass;
        $imageModel->itemId = $this->owner->id;
        $imageModel->name = 'temp';
        if ($imageModel->save()) {
            $fileName = $imageModel->id . '.' . $file->extension;
            $imageModel->name = $fileName;
            if ($imageModel->save()) {
                return $imageModel->id;
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
        $this->file = UploadedFile::getInstance($model, $this->imageVariable);
        if ($this->file instanceof UploadedFile) {
            $model->{$this->imageVariable} = $this->file;
        }
    }

    /**
     * This method is called after models save.
     */
    public function afterSave()
    {
        $this->saveImage();
    }
}
