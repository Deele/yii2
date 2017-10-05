<?php
namespace yii\web;

use yii\base\Event;

/**
 * This event class is used for Events triggered by the [[AssetBundle]] class.
 *
 * @author Nils L. <deele@tuta.io>
 */
class AssetBundleEvent extends Event
{
    /**
     * @var \yii\web\AssetBundle the related asset bundle.
     */
    public $assetBundle;
    /**
     * @var \yii\web\AssetManager the asset manager to perform the asset publishing.
     */
    public $assetManager;
}
