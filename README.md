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
        $this->createTable('images', [
            'id' => Schema::TYPE_PK,
            'itemId' => Schema::TYPE_INTEGER . ' NOT NULL',
            'image' => Schema::TYPE_STRING . '(255) NOT NULL',
            'path' => Schema::TYPE_STRING . '(255) NOT NULL',
            'size' => Schema::TYPE_STRING . '(255) NOT NULL',
        ]);
```

## Create model

Generate Active Record model for new `image` table

## Configuring

Attach the behavior to your model class:

```php
use tpmanc\filebehavior\FileBehavior;

\\ ...

    public $file;

    public function behaviors()
    {
        return [
            'FileBehavior' => [
                'class' => FileBehavior::className(),
                'fileModel' => 'models\Image',
                'fileVar' => 'file',
                'fileFolder' => '@upload/images',
            ],
        ];
    }

    public function rules()
    {
        ['file', 'file', 'extensions' => ['png', 'jpg'], 'maxSize' => 1024*1024*1024],
    }

```

The file will be located in `@upload/images/{id from image table}.jpg`

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
        ['file', 'file', 'extensions' => ['png', 'jpg'], 'maxSize' => 1024*1024*1024],
    }
```

Result - 3 images:

- `@upload/images/original/{id from image table}.jpg`

- `@upload/images/big/{id from image table}.jpg`

- `@upload/images/small/{id from image table}.jpg`

## View file

Example of view file

```html
<?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]) ?>
    <?= $form->field($model, 'file')->fileInput() ?>
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>
<?php ActiveForm::end(); ?>
```  
