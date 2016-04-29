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
use yii\web\ServerErrorHttpException;

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
    public $multiple = false;
    public $orderField = false;
    public $isHashEnabled = true;

    private $files;

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
     * @param string $size Image size title
     * @param integer $count Count of images
     * @return string Path to first item image
     */
    public function getImages($size = 'default', $count = false, $returnFalseOnNoImage = false)
    {
        $imageModelClass = $this->imageModel;
        $imageSizeModel = $this->imageSizeModel;
        $imageTableName = $imageModelClass::tableName();
        $tableSizeName = $imageSizeModel::tableName();
        $sql = "SELECT
                    {{{$imageTableName}}}.*,
                    {{{$tableSizeName}}}.[[path]],
                    {{{$tableSizeName}}}.[[size]]
                FROM
                    {{{$imageTableName}}}
                LEFT JOIN {{{$tableSizeName}}} ON {{{$tableSizeName}}}.[[imageId]] = {{{$imageTableName}}}.[[id]]
                WHERE
                    [[itemId]] = :itemid AND
                    [[size]] = :size";
        if ($this->orderField !== false) {
            $sql .= ' ORDER BY [[' . $this->orderField . ']] ASC';
        }
        if ($count !== false) {
            $sql .= " LIMIT $count";
        }
        $images = $imageModelClass::findBySql($sql, [
            ':itemid' => $this->owner->id,
            ':size' => $size,
        ])->asArray()->all();

        $result = [];
        if (empty($images)) {
            if ($returnFalseOnNoImage) {
                $result = false;
            } else {
                $result[0] = Yii::getAlias($this->noImagePath);
            }
        } else {
            foreach ($images as $image) {
                $result[$image['id']] = Yii::getAlias($this->webImageFolder . $image['path'] . $image['id'] . '.' . $image['extension']);
            }
        }

        return $result;
    }

    /**
     * Get first image of item
     * @param string $size Image size title
     * @return string Path to first item image
     */
    public function getImage($size = 'default', $returnFalseOnNoImage = false)
    {
        $imageModelClass = $this->imageModel;
        $imageSizeModel = $this->imageSizeModel;
        $imageTableName = $imageModelClass::tableName();
        $tableSizeName = $imageSizeModel::tableName();
        $sql = "SELECT
                    {{{$imageTableName}}}.*,
                    {{{$tableSizeName}}}.[[path]],
                    {{{$tableSizeName}}}.[[size]]
                FROM
                    {{{$imageTableName}}}
                LEFT JOIN {{{$tableSizeName}}} ON {{{$tableSizeName}}}.[[imageId]] = {{{$imageTableName}}}.[[id]]
                WHERE
                    [[itemId]] = :itemid AND
                    [[size]] = :size LIMIT 1";
        $image = $imageModelClass::findBySql($sql, [
            ':itemid' => $this->owner->id,
            ':size' => $size,
        ])->asArray()->one();

        if ($image === null) {
            if ($returnFalseOnNoImage) {
                return false;
            } else {
                return Yii::getAlias($this->noImagePath);
            }
        } else {
            return Yii::getAlias($this->webImageFolder . $image['path'] . $image['id'] . '.' . $image['extension']);
        }
    }

    /**
     * Return full path to uplaod folder
     * @return string Path to upload folder
     */
    private function getFolderPath()
    {
        return Yii::getAlias($this->imageFolder);
    }

    // TODO: create exceptions
    /**
     * Save image to disk
     */
    private function saveImage($file)
    {
        // $file = $this->owner->{$this->imageVariable};
        $error = false;
        if ($file !== null && $file !== '') {
            $imageModelClass = $this->imageModel;
            $transaction = $imageModelClass::getDb()->beginTransaction();
            if ($this->isHashEnabled) {
                $hash = md5_file($file->tempName);
                $hashDir = $hash[0] . $hash[1] . '/' . $hash[2] . $hash[4];
            } else {
                $hash = false;
            }

            $successDownloaded = [];
            // save files
            if ($this->imageSizes !== false && is_array($this->imageSizes)) {
                $sizeCount = count($this->imageSizes);
                $sizeNumber = 0;
                $saveInfo = $this->saveToDb($file, $hash);
                $imageId = $saveInfo['id'];
                $isExist = $saveInfo['exist'];
                if ($imageId === false) {
                    throw new ServerErrorHttpException("Cant save to DB");
                }
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
                    if ($hash !== false) {
                        $hashDir = $hash[0] . $hash[1] . '/' . $hash[2] . $hash[4];
                        $path .= $hashDir . '/';
                    }
                    if (!file_exists(Yii::getAlias($path))) {
                        mkdir(Yii::getAlias($path), 0777, true);
                    }
                    // save to DB
                    if ($isExist === false) {
                        $error = $this->addImageSize($imageId, $path, $sizeName);
                    } else {
                        $error = false;
                    }
                    $fileName = $imageId . '.' . $file->extension;
                    if ($error === false) {
                        if (isset($size['folder']) && $size['folder'] !== '') {
                            $filePath = $this->getFolderPath() . '/' . $size['folder'];
                            if ($hash !== false) {
                                $filePath .= '/' . $hashDir;
                            }
                            $filePath .= '/' . $fileName;
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
                $saveInfo = $this->saveToDb($file, $hash);
                $imageId = $saveInfo['id'];
                $isExist = $saveInfo['exist'];
                if ($isExist === false) {
                    $error = $this->addImageSize($imageId, $path);
                } else {
                    $error = false;
                }
                if ($imageId !== false) {
                    $fileName = $imageId . '.' . $file->extension;

                    $filePath = $this->getFolderPath();
                    if ($hash !== false) {
                        $filePath .= '/' . $hashDir;
                    }
                    $filePath .= '/' . $fileName;

                    $filePath = $this->getFolderPath() . '/' . $fileName;
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
    private function saveToDb($file, $hash)
    {
        $imageModelClass = $this->imageModel;

        if ($this->multiple === false) {
            // find exist image record
            $imageModel = $imageModelClass::find()->where([$this->linkItemColumn => $this->owner->id])->one();
            if ($imageModel !== null) {
                return [
                    'exist' => true,
                    'id' => $imageModel->id,
                ];
            }
        }
        
        $imageModel = new $imageModelClass;
        $imageModel->itemId = $this->owner->id;
        $imageModel->extension = $file->extension;
        if ($hash !== false) {
            $imageModel->hash = $hash;
        }
        $imageModel->order = 0;
        if ($imageModel->save()) {
            return [
                'exist' => false,
                'id' => $imageModel->id,
            ];
        } else {
            var_dump($imageModel->errors);
            die();
        }
        return false;
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
     * Delete image by id
     * @param integer $imageId Image id
     */
    public function deleteImage($imageId)
    {
        $imageModelClass = $this->imageModel;
        $imageSizeClass = $this->imageSizeModel;

        $image = $imageModelClass::findOne($imageId);
        if ($image !== null) {
            $sizes = $imageSizeClass::find()->where(['imageId' => $image->id])->all();
            foreach ($sizes as $size) {
                unlink($this->getFolderPath() . $size->path . '/' . $image->id . '.' . $image->extension);
                $size->delete();
            }
            $image->delete();
        }
    }

    /**
     * Delete all images
     */
    public function deleteAll()
    {
        $imageModelClass = $this->imageModel;
        $imageSizeClass = $this->imageSizeModel;

        $images = $imageModelClass::find()->where([$this->linkItemColumn => $this->owner->id])->all();
        foreach ($images as $image) {
            $sizes = $imageSizeClass::find()->where(['imageId' => $image->id])->all();
            foreach ($sizes as $size) {
                try {
                    unlink($this->getFolderPath() . $size->path . '/' . $image->id . '.' . $image->extension);
                } catch (\Exception $e) {}
                $size->delete();
            }
            $image->delete();
        }
    }

    /**
     * Invoked before delete model
     */
    public function beforeDelete()
    {
        $this->deleteAll();
    }

    /**
     * Invoked before validation starts.
     */
    public function beforeValidate()
    {
        /** @var BaseActiveRecord $model */
        $model = $this->owner;
        $this->files = UploadedFile::getInstances($model, $this->imageVariable);
        if ($this->files instanceof UploadedFile) {
            $model->{$this->imageVariable} = $this->files;
        }
    }

    /**
     * This method is called after models save.
     */
    public function afterSave()
    {
        foreach ($this->files as $file) {
            $this->saveImage($file);
        }
    }
}
