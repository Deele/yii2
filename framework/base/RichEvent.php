<?php
namespace yii\base;

use yii\helpers\ArrayHelper;

/**
 * RichEvent allows creation of events that carry contextual data.
 *
 * @author Deele <deele@tuta.io>
 */
class RichEvent extends Event
{

    /**
     * @var array with contextual data. Defaults to empty array.
     */
    public $contextData = [];

    /**
     * Retrieves the value from context data array
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getContextData($key, $default = null)
    {
        return ArrayHelper::getValue($key, $default);
    }
}
