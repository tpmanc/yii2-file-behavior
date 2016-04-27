# yii2-file-behavior
Yii 2 file uploading

## Install via Composer

Run the following command

```bash
$ composer require tpmanc/yii2-file-behavior "*"
```

or add

```bash
$ "tpmanc/yii2-file-behavior": "*"
```

to the require section of your `composer.json` file.

## Migrations

Create migration by following command

```bash
$ yii migrate/create images
```

Open the `/path/to/migrations/m_xxxxxx_xxxxxx_images.php` file 
and add following code to `up()` method


```php
        $this->createTable('image', [
            'id' => Schema::TYPE_PK,
            'itemId' => Schema::TYPE_INTEGER . ' NOT NULL',
            'order' => Schema::TYPE_INTEGER . ' NOT NULL',
            'extension' => Schema::TYPE_STRING . '(10) NOT NULL',
            'hash' => Schema::TYPE_STRING . '(32) NOT NULL',
        ]);

        $this->createTable('imageSize', [
            'id' => Schema::TYPE_PK,
            'imageId' => Schema::TYPE_INTEGER . ' NOT NULL',
            'path' => Schema::TYPE_STRING . '(255) NOT NULL',
            'size' => Schema::TYPE_STRING . '(255) NOT NULL',
        ]);
```

## Create model

Generate Active Record model for new `image` and `imageSize` tables

## Configuring

Attach the behavior to your model class:

```php
use tpmanc\filebehavior\ImageBehavior;

\\ ...

    public $file;

    public function behaviors()
    {
        return [
            'FileBehavior' => [
                'class' => FileBehavior::className(),
                'imageModel' => 'models\Image',
                'imageSizeModel' => 'models\ImageSize',
                'imageVariable' => 'file',
                'imageFolder' => '@upload',
                'webImageFolder' => '@webupload',
                'noImagePath' => '@webupload/no-image.png',
            ],
        ];
    }

    public function rules()
    {
        ['file', 'file', 'extensions' => ['png', 'jpg'], 'maxSize' => 1024*1024*1024, 'maxFiles' => 4],
    }

```

If file hash will be like "6e3c797abee0ff2803ef1f952f187d2f" 
the file will be located in `@upload/images/6e/3c/{id from image table}.jpg`

To save several sizes of image add:

```php
    public $file;

    public function behaviors()
    {
        return [
            'FileBehavior' => [
                'class' => FileBehavior::className(),
                'fileModel' => 'models\Image',
                'fileVar' => 'file',
                'fileFolder' => '@upload/images',
                'imageSizes' => [
                    'original' => [
                        'folder' => 'original',
                    ],
                    'big' => [
                        'width' => 800,
                        'height' => 600,
                        'folder' => 'big',
                    ],
                    'small' => [
                        'width' => 64,
                        'height' => 64,
                        'folder' => 'small',
                    ],
                ],
            ],
        ];
    }

    public function rules()
    {
        ['file', 'file', 'extensions' => ['png', 'jpg'], 'maxSize' => 1024*1024*1024, 'maxFiles' => 1],
    }
```

If file hash will be like "6e3c797abee0ff2803ef1f952f187d2f" - result 3 images:

- `@upload/images/original/6e/3c/{id from image table}.jpg`

- `@upload/images/big/6e/3c/{id from image table}.jpg`

- `@upload/images/small/6e/3c/{id from image table}.jpg`

## View file

Example of view file

```html
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]) ?>
    <?= $form->field($model, 'file[]')->fileInput(['multiple' => true, 'accept' => 'image/*']) ?>
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>
<?php ActiveForm::end(); ?>
```  

## Geting images

Get single image:

```html
<img src="<?= $model->getImage('original') ?>" alt="">
<img src="<?= $model->getImage('big') ?>" alt="">
<img src="<?= $model->getImage('small') ?>" alt="">
```

Get all images:

```html
<?php foreach ($model->getImages('original') as $image) { ?>
    <img src="<?= $image ?>" alt="">
<?php } ?>
```

Get `count` images:

```html
<?php $count = 5;?>
...
<?php foreach ($model->getImages('original', $count) as $image) { ?>
    <img src="<?= $image ?>" alt="">
<?php } ?>
```

Return `false` when image does not exist:

For single image:

```php
if ($model->getImage('original', true) === false) {
    ...
}
```

For all images:

```php
$count = false;
foreach ($model->getImages('original', $count, true) as $image) {
    if ($image === false) {
        ...
    }
}
```
