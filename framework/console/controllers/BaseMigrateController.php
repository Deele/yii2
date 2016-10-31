<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\console\controllers;

use Yii;
use yii\base\RichEvent;
use yii\base\BaseMigrator;
use yii\di\Instance;
use yii\base\InvalidConfigException;
use yii\console\Exception;
use yii\console\Controller;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * BaseMigrateController is the base class for migrate controllers.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Deele <deele@tuta.io>
 * @since 2.0
 */
abstract class BaseMigrateController extends Controller
{

    /**
     * @var string the default command action.
     */
    public $defaultAction = 'up';
    /**
     * @var string the directory containing the migration classes. This can be either
     * a path alias or a directory path.
     *
     * If you have set up [[migrationNamespaces]], you may set this field to `null` in order
     * to disable usage of migrations that are not namespaced.
     */
    public $migrationPath = '@app/migrations';
    /**
     * @var string the template file for generating new migrations.
     * This can be either a path alias (e.g. "@app/migrations/template.php")
     * or a file path.
     */
    public $templateFile;
    /**
     * @var \common\helpers\Migrator
     */
    protected $_migrator;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(
            parent::options($actionID),
            ['migrationPath'], // global for all actions
            $actionID === 'create' ? ['templateFile'] : [] // action create
        );
    }

    /**
     * This method is invoked right before an action is to be executed (after all possible filters.)
     * It checks the existence of the [[migrationPath]].
     * @param \yii\base\Action $action the action to be executed.
     * @throws InvalidConfigException if directory specified in migrationPath doesn't exist and action isn't "create".
     * @return boolean whether the action should continue to be executed.
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $this->_migrator = Instance::ensure($this->_migrator, BaseMigrator::className());

            $version = Yii::getVersion();
            $this->stdout("Yii Migration Tool (based on Yii v{$version})\n\n");

            return true;
        } else {
            return false;
        }
    }

    /**
     * Sets up migrator event handlers for upgrade
     */
    protected function prepareUpgradeHandlers()
    {
        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_UPGRADE,
            function (RichEvent $event) {
                $n = $event->getContextData('count');
                $total = $event->getContextData('totalCount');
                if ($n === $total) {
                    $this->stdout("Total $n new " . ($n === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
                } else {
                    $this->stdout("Total $n out of $total new " . ($total === 1 ? 'migration' : 'migrations') . " to be applied:\n", Console::FG_YELLOW);
                }

                foreach ($event->getContextData('migrations') as $migration) {
                    $this->stdout("\t$migration\n");
                }
                $this->stdout("\n");

                $event->contextData['runUpgrade'] = $this->confirm('Apply the above ' . ($n === 1 ? 'migration' : 'migrations') . '?');
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_MIGRATE_UPGRADE,
            function (RichEvent $event) {
                $class = $event->getContextData('class');
                $this->stdout("*** applying $class\n", Console::FG_YELLOW);
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_UPGRADE,
            function (RichEvent $event) {
                $n = $event->getContextData('count');
                if ($event->getContextData('success', false)) {
                    $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') ." applied.\n", Console::FG_GREEN);
                    $this->stdout("\nMigrated up successfully.\n", Console::FG_GREEN);
                }
                else {
                    $applied = $event->getContextData('applied');
                    $this->stdout("\n$applied from $n " . ($applied === 1 ? 'migration was' : 'migrations were') ." applied.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                }
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_MIGRATE_UPGRADE,
            function (RichEvent $event) {
                $class = $event->getContextData('class');
                $time = $event->getContextData('end') - $event->getContextData('start');
                if ($event->getContextData('success')) {
                    $this->stdout("*** applied $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
                }
                else {
                    $this->stdout("*** failed to apply $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
                }
            }
        );
    }

    /**
     * Upgrades the application by applying new migrations.
     * For example,
     *
     * ```
     * yii migrate     # apply all new migrations
     * yii migrate 3   # apply the first 3 new migrations
     * ```
     *
     * @param integer $limit the number of new migrations to be applied. If 0, it means
     * applying all available new migrations.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionUp($limit = 0)
    {
        $this->prepareUpgradeHandlers();
        $upgrade = $this->_migrator->upgrade($limit);
        if ($upgrade === false) {
            return self::EXIT_CODE_ERROR;
        }
        elseif (is_null($upgrade)) {
            $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Sets up event migrator handlers for downgrade
     */
    protected function prepareDowngradeHandlers()
    {
        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_DOWNGRADE,
            function (RichEvent $event) {
                $n = $event->getContextData('totalCount');
                $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be reverted:\n", Console::FG_YELLOW);
                foreach ($event->getContextData('migrations') as $migration) {
                    $this->stdout("\t$migration\n");
                }
                $this->stdout("\n");

                $event->contextData['runDowngrade'] = $this->confirm('Revert the above ' . ($n === 1 ? 'migration' : 'migrations') . '?');
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_MIGRATE_DOWNGRADE,
            function (RichEvent $event) {
                $class = $event->getContextData('class');
                $this->stdout("*** reverting $class\n", Console::FG_YELLOW);
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_DOWNGRADE,
            function (RichEvent $event) {
                $n = $event->getContextData('totalCount');
                if ($event->getContextData('success')) {
                    $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') ." reverted.\n", Console::FG_GREEN);
                    $this->stdout("\nMigrated down successfully.\n", Console::FG_GREEN);
                }
                else {
                    $reverted = $event->getContextData('reverted');
                    $this->stdout("\n$reverted from $n " . ($reverted === 1 ? 'migration was' : 'migrations were') ." reverted.\n", Console::FG_RED);
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                }
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_MIGRATE_DOWNGRADE,
            function (RichEvent $event) {
                $class = $event->getContextData('class');
                $time = $event->getContextData('end') - $event->getContextData('start');
                if ($event->getContextData('success')) {
                    $this->stdout("*** reverted $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_GREEN);
                }
                else {
                    $this->stdout("*** failed to revert $class (time: " . sprintf('%.3f', $time) . "s)\n\n", Console::FG_RED);
                }
            }
        );
    }

    /**
     * Downgrades the application by reverting old migrations.
     * For example,
     *
     * ```
     * yii migrate/down     # revert the last migration
     * yii migrate/down 3   # revert the last 3 migrations
     * yii migrate/down all # revert all migrations
     * ```
     *
     * @param integer $limit the number of migrations to be reverted. Defaults to 1,
     * meaning the last applied migration will be reverted.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionDown($limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The step argument must be greater than 0.');
            }
        }

        $this->prepareDowngradeHandlers();
        $downgrade = $this->_migrator->downgrade($limit);
        if ($downgrade === false) {
            return self::EXIT_CODE_ERROR;
        }
        elseif (is_null($downgrade)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * yii migrate/redo     # redo the last applied migration
     * yii migrate/redo 3   # redo the last 3 applied migrations
     * yii migrate/redo all # redo all migrations
     * ```
     *
     * @param integer $limit the number of migrations to be redone. Defaults to 1,
     * meaning the last applied migration will be redone.
     * @throws Exception if the number of the steps specified is less than 1.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionRedo($limit = 1)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The step argument must be greater than 0.');
            }
        }

        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_REDO,
            function (RichEvent $event) {
                $n = $event->getContextData('totalCount');
                $this->stdout("Total $n " . ($n === 1 ? 'migration' : 'migrations') . " to be redone:\n", Console::FG_YELLOW);
                foreach ($event->getContextData('migrations') as $migration) {
                    $this->stdout("\t$migration\n");
                }
                $this->stdout("\n");

                $event->contextData['runRedo'] = $this->confirm('Redo the above ' . ($n === 1 ? 'migration' : 'migrations') . '?');
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_REDO,
            function (RichEvent $event) {
                $n = $event->getContextData('totalCount');
                if ($event->getContextData('success') == false) {
                    $this->stdout("\nMigration failed. The rest of the migrations are canceled.\n", Console::FG_RED);
                }
                else {
                    $this->stdout("\n$n " . ($n === 1 ? 'migration was' : 'migrations were') ." redone.\n", Console::FG_GREEN);
                    $this->stdout("\nMigration redone successfully.\n", Console::FG_GREEN);
                }

            }
        );

        $this->prepareUpgradeHandlers();
        $this->prepareDowngradeHandlers();
        $redo = $this->_migrator->redo($limit);
        if ($redo === false) {
            return self::EXIT_CODE_ERROR;
        }
        elseif (is_null($redo)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Upgrades or downgrades till the specified version.
     *
     * Can also downgrade versions to the certain apply time in the past by providing
     * a UNIX timestamp or a string parseable by the strtotime() function. This means
     * that all the versions applied after the specified certain time would be reverted.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * yii migrate/to 101129_185401                          # using timestamp
     * yii migrate/to m101129_185401_create_user_table       # using full name
     * yii migrate/to 1392853618                             # using UNIX timestamp
     * yii migrate/to "2014-02-15 13:00:50"                  # using strtotime() parseable string
     * yii migrate/to app\migrations\M101129185401CreateUser # using full namespace name
     * ```
     *
     * @param string $version either the version name or the certain time value in the past
     * that the application should be migrated to. This can be either the timestamp,
     * the full name of the migration, the UNIX timestamp, or the parseable datetime
     * string.
     * @throws Exception if the version argument is invalid.
     *
     * @return integer the status of the action execution. 0 means normal, other values mean abnormal.
     */
    public function actionTo($version)
    {
        $this->prepareUpgradeHandlers();
        $this->prepareDowngradeHandlers();
        try {
            $to = $this->_migrator->to($version);
        } catch (\yii\base\Exception $e) {
            $this->stdout("\n{$e->getMessage()}\n", Console::FG_RED);

            return self::EXIT_CODE_ERROR;
        } catch (InvalidParamException $e) {
            throw new Exception("The version argument must be either a timestamp (e.g. 101129_185401),\n the full name of a migration (e.g. m101129_185401_create_user_table),\n the full namespaced name of a migration (e.g. app\\migrations\\M101129185401CreateUserTable),\n a UNIX timestamp (e.g. 1392853000), or a datetime string parseable\nby the strtotime() function (e.g. 2014-02-15 13:00:50).");
        }

        if (is_null($to)) {
            list($type, $value) = $this->_migrator->parseVersionString($version);
            switch ($type) {
                case 'namespace':
                case 'name':
                    $this->stdout("Already at '$value'. Nothing needs to be done.\n", Console::FG_YELLOW);
                    break;
                case 'timestamp':
                case 'time':
                    $this->stdout("Nothing needs to be done.\n", Console::FG_GREEN);
                    break;
                default:
            }
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Modifies the migration history to the specified version.
     *
     * No actual migration will be performed.
     *
     * ```
     * yii migrate/mark 101129_185401                        # using timestamp
     * yii migrate/mark m101129_185401_create_user_table     # using full name
     * yii migrate/to app\migrations\M101129185401CreateUser # using full namespace name
     * ```
     *
     * @param string $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * @return integer CLI exit code
     * @throws Exception if the version argument is invalid or the version cannot be found.
     */
    public function actionMark($version)
    {
        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_MARK,
            function (RichEvent $event) {
                $originalVersion = $event->getContextData('originalVersion');
                $event->contextData['runMark'] = $this->confirm("Set migration history at $originalVersion?");
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_MARK,
            function (RichEvent $event) {
                $originalVersion = $event->getContextData('originalVersion');
                $this->stdout("The migration history is set at $originalVersion.\nNo actual migration was performed.\n", Console::FG_GREEN);
            }
        );

        $mark = $this->_migrator->mark($version);
        if ($mark === false) {
            $this->stdout("Unable to find the version '$version'.\n", Console::FG_RED);

            return self::EXIT_CODE_ERROR;
        }
        elseif (is_null($mark)) {
            $this->stdout("Already at '$version'. Nothing needs to be done.\n", Console::FG_YELLOW);
        }

        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Displays the migration history.
     *
     * This command will show the list of migrations that have been applied
     * so far. For example,
     *
     * ```
     * yii migrate/history     # showing the last 10 migrations
     * yii migrate/history 5   # showing the last 5 migrations
     * yii migrate/history all # showing the whole history
     * ```
     *
     * @param integer $limit the maximum number of migrations to be displayed.
     * If it is "all", the whole migration history will be displayed.
     * @throws \yii\console\Exception if invalid limit value passed
     */
    public function actionHistory($limit = 10)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The limit must be greater than 0.');
            }
        }

        $migrations = $this->_migrator->history($limit);

        if (empty($migrations)) {
            $this->stdout("No migration has been done before.\n", Console::FG_YELLOW);
        } else {
            $n = count($migrations);
            if ($limit > 0) {
                $this->stdout("Showing the last $n applied " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Total $n " . ($n === 1 ? 'migration has' : 'migrations have') . " been applied before:\n", Console::FG_YELLOW);
            }
            foreach ($migrations as $version => $time) {
                $this->stdout("\t(" . date('Y-m-d H:i:s', $time) . ') ' . $version . "\n");
            }
        }
    }

    /**
     * Displays the un-applied new migrations.
     *
     * This command will show the new migrations that have not been applied.
     * For example,
     *
     * ```
     * yii migrate/new     # showing the first 10 new migrations
     * yii migrate/new 5   # showing the first 5 new migrations
     * yii migrate/new all # showing all new migrations
     * ```
     *
     * @param integer $limit the maximum number of new migrations to be displayed.
     * If it is `all`, all available new migrations will be displayed.
     * @throws \yii\console\Exception if invalid limit value passed
     */
    public function actionNew($limit = 10)
    {
        if ($limit === 'all') {
            $limit = null;
        } else {
            $limit = (int) $limit;
            if ($limit < 1) {
                throw new Exception('The limit must be greater than 0.');
            }
        }

        $migrations = $this->_migrator->newMigrations();

        if (empty($migrations)) {
            $this->stdout("No new migrations found. Your system is up-to-date.\n", Console::FG_GREEN);
        } else {
            $n = count($migrations);
            if ($limit && $n > $limit) {
                $migrations = array_slice($migrations, 0, $limit);
                $this->stdout("Showing $limit out of $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            } else {
                $this->stdout("Found $n new " . ($n === 1 ? 'migration' : 'migrations') . ":\n", Console::FG_YELLOW);
            }

            foreach ($migrations as $migration) {
                $this->stdout("\t" . $migration . "\n");
            }
        }
    }

    /**
     * Creates a new migration.
     *
     * This command creates a new migration using the available migration template.
     * After using this command, developers should modify the created migration
     * skeleton by filling up the actual migration logic.
     *
     * ```
     * yii migrate/create create_user_table
     * ```
     *
     * In order to generate a namespaced migration, you should specify a namespace before the migration's name.
     * Note that backslash (`\`) is usually considered a special character in the shell, so you need to escape it
     * properly to avoid shell errors or incorrect behavior.
     * For example:
     *
     * ```
     * yii migrate/create 'app\\migrations\\createUserTable'
     * ```
     *
     * In case [[migrationPath]] is not set and no namespace is provided, the first entry of [[migrationNamespaces]] will be used.
     *
     * @param string $name the name of the new migration. This should only contain
     * letters, digits, underscores and/or backslashes.
     *
     * Note: If the migration name is of a special form, for example create_xxx or
     * drop_xxx, then the generated migration file will contain extra code,
     * in this case for creating/dropping tables.
     *
     * @throws Exception if the name argument is invalid.
     */
    public function actionCreate($name)
    {
        $this->_migrator->on(
            BaseMigrator::EVENT_BEFORE_CREATE,
            function (RichEvent $event) {
                $file = $event->getContextData('file');
                $event->contextData['runCreate'] = $this->confirm("Create new migration '$file'?");
            }
        );

        $this->_migrator->on(
            BaseMigrator::EVENT_AFTER_CREATE,
            function () {
                $this->stdout("New migration created successfully.\n", Console::FG_GREEN);
            }
        );

        try {
            $this->_migrator->create($name);
        } catch (InvalidParamException $e) {
            throw new Exception('The migration name should contain letters, digits, underscore and/or backslash characters only.');
        }
    }
}
    
