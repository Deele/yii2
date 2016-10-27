<?php
/**
 * @link http://www.yiiframework.com/
 */

namespace yii\base;

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
}
