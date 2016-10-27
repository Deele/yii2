<?php
namespace yii\base;

use Yii;
use yii\helpers\FileHelper;
use yii\web\View;

/**
 * BaseMigrator is the base class for migrators.
 *
 * @author Deele <deele@tuta.io>
 */
abstract class BaseMigrator extends Component
{
    /**
     * @event RichEvent an event raised right before executing a upgrade.
     * You may set [[RichEvent::contextData]] array `runUpgrade` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_UPGRADE = 'beforeUpgrade';
    /**
     * @event RichEvent an event raised right after executing a upgrade.
     */
    const EVENT_AFTER_UPGRADE = 'afterUpgrade';
    /**
     * @event RichEvent an event raised right before executing a migrate upgrade.
     * You may set [[RichEvent::contextData]] array `runMigrateUpgrade` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_MIGRATE_UPGRADE = 'beforeMigrateUpgrade';
    /**
     * @event RichEvent an event raised right after executing a migrate upgrade.
     */
    const EVENT_AFTER_MIGRATE_UPGRADE = 'afterMigrateUpgrade';
    /**
     * @event RichEvent an event raised right before executing a downgrade.
     * You may set [[RichEvent::contextData]] array `runDowngrade` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_DOWNGRADE = 'beforeDowngrade';
    /**
     * @event RichEvent an event raised right after executing a downgrade.
     */
    const EVENT_AFTER_DOWNGRADE = 'afterDowngrade';
    /**
     * @event RichEvent an event raised right before executing a migrate downgrade.
     * You may set [[RichEvent::contextData]] array `runMigrateDowngrade` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_MIGRATE_DOWNGRADE = 'beforeMigrateDowngrade';
    /**
     * @event RichEvent an event raised right after executing a migrate downgrade.
     */
    const EVENT_AFTER_MIGRATE_DOWNGRADE = 'afterMigrateDowngrade';
    /**
     * @event RichEvent an event raised right before executing a redo.
     * You may set [[RichEvent::contextData]] array `runRedo` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_REDO = 'beforeRedo';
    /**
     * @event RichEvent an event raised right after executing a redo.
     */
    const EVENT_AFTER_REDO = 'afterRedo';
    /**
     * @event RichEvent an event raised right before executing a mark.
     * You may set [[RichEvent::contextData]] array `runMark` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_MARK = 'beforeMark';
    /**
     * @event RichEvent an event raised right after executing a mark.
     */
    const EVENT_AFTER_MARK = 'afterMark';
    /**
     * @event RichEvent an event raised right before executing a create.
     * You may set [[RichEvent::contextData]] array `runCreate` key value to be false to cancel the action execution.
     */
    const EVENT_BEFORE_CREATE = 'beforeCreate';
    /**
     * @event RichEvent an event raised right after executing a create.
     */
    const EVENT_AFTER_CREATE = 'afterCreate';
    /**
     * The name of the dummy migration that marks the beginning of the whole migration history.
     */
    const BASE_MIGRATION = 'm000000_000000_base';

    /**
     * @var string the directory containing the migration classes. This can be either
     * a path alias or a directory path.
     *
     * If you have set up [[migrationNamespaces]], you may set this field to `null` in order
     * to disable usage of migrations that are not namespaced.
     */
    public $migrationPath = '@app/migrations';

    /**
     * @var array list of namespaces containing the migration classes.
     *
     * Migration namespaces should be resolvable as a path alias if prefixed with `@`, e.g. if you specify
     * the namespace `app\migrations`, the code `Yii::getAlias('@app/migrations')` should be able to return
     * the file path to the directory this namespace refers to.
     *
     * For example:
     *
     * ```php
     * [
     *     'app\migrations',
     *     'some\extension\migrations',
     * ]
     * ```
     *
     * @since 2.0.10
     */
    public $migrationNamespaces = [];

    /**
     * @var string the template file for generating new migrations.
     * This can be either a path alias (e.g. "@app/migrations/template.php")
     * or a file path.
     */
    public $templateFile;

    /**
     * @var View the view object that can be used to render views or view files.
     */
    protected $_view;

    /**
     * This method is invoked before upgrade starts.
     *
     * @param array $upgradeStatus
     *
     * @return bool
     */
    public function beforeUpgrade($upgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $upgradeStatus;
        $event->contextData['runUpgrade'] = true;
        $this->trigger(
            self::EVENT_BEFORE_UPGRADE,
            $event
        );
        return $event->contextData['runUpgrade'];
    }

    /**
     * This method is invoked after upgrade finished.
     *
     * @param array $upgradeStatus
     *
     * @return bool
     */
    public function afterUpgrade($upgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $upgradeStatus;
        $this->trigger(
            self::EVENT_AFTER_UPGRADE,
            $event
        );
    }

    /**
     * This method is invoked before migrate upgrade starts.
     *
     * @param array $migrateUpgradeStatus
     *
     * @return bool
     */
    public function beforeMigrateUpgrade($migrateUpgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $migrateUpgradeStatus;
        $event->contextData['runMigrateUpgrade'] = true;
        $this->trigger(
            self::EVENT_BEFORE_MIGRATE_UPGRADE,
            $event
        );
        return $event->contextData['runMigrateUpgrade'];
    }

    /**
     * This method is invoked after migrate upgrade finished.
     *
     * @param array $migrateUpgradeStatus
     */
    public function afterMigrateUpgrade($migrateUpgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $migrateUpgradeStatus;
        $this->trigger(
            self::EVENT_AFTER_MIGRATE_DOWNGRADE,
            $event
        );
    }

    /**
     * This method is invoked before downgrade starts.
     *
     * @param array $downgradeStatus
     *
     * @return bool
     */
    public function beforeDowngrade($downgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $downgradeStatus;
        $event->contextData['runDowngrade'] = true;
        $this->trigger(
            self::EVENT_BEFORE_DOWNGRADE,
            $event
        );
        return $event->contextData['runDowngrade'];
    }

    /**
     * This method is invoked after downgrade finished.
     *
     * @param array $downgradeStatus
     *
     * @return bool
     */
    public function afterDowngrade($downgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $downgradeStatus;
        $this->trigger(
            self::EVENT_AFTER_DOWNGRADE,
            $event
        );
    }

    /**
     * This method is invoked before migrate downgrade starts.
     *
     * @param array $migrateDowngradeStatus
     *
     * @return bool
     */
    public function beforeMigrateDowngrade($migrateDowngradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $migrateDowngradeStatus;
        $event->contextData['runMigrateDowngrade'] = true;
        $this->trigger(
            self::EVENT_BEFORE_MIGRATE_DOWNGRADE,
            $event
        );
        return $event->contextData['runMigrateDowngrade'];
    }

    /**
     * This method is invoked after migrate downgrade finished.
     *
     * @param array $migrateDowngradeStatus
     */
    public function afterMigrateDowngrade($migrateDowngradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $migrateDowngradeStatus;
        $this->trigger(
            self::EVENT_AFTER_MIGRATE_UPGRADE,
            $event
        );
    }

    /**
     * This method is invoked before redo starts.
     *
     * @param array $redoStatus
     *
     * @return bool
     */
    public function beforeRedo($redoStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $redoStatus;
        $event->contextData['runRedo'] = true;
        $this->trigger(
            self::EVENT_BEFORE_REDO,
            $event
        );
        return $event->contextData['runRedo'];
    }

    /**
     * This method is invoked after redo finished.
     *
     * @param array $redoStatus
     *
     * @return bool
     */
    public function afterRedo($redoStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $redoStatus;
        $this->trigger(
            self::EVENT_AFTER_REDO,
            $event
        );
    }

    /**
     * This method is invoked before mark starts.
     *
     * @param array $markStatus
     *
     * @return bool
     */
    public function beforeMark($markStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $markStatus;
        $event->contextData['runMark'] = true;
        $this->trigger(
            self::EVENT_BEFORE_MARK,
            $event
        );
        return $event->contextData['runMark'];
    }

    /**
     * This method is invoked after mark finished.
     *
     * @param array $markStatus
     *
     * @return bool
     */
    public function afterMark($markStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $markStatus;
        $this->trigger(
            self::EVENT_AFTER_MARK,
            $event
        );
    }

    /**
     * This method is invoked before create starts.
     *
     * @param array $createStatus
     *
     * @return bool
     */
    public function beforeCreate($createStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $createStatus;
        $event->contextData['runCreate'] = true;
        $this->trigger(
            self::EVENT_BEFORE_CREATE,
            $event
        );
        return $event->contextData['runCreate'];
    }

    /**
     * This method is invoked after create finished.
     *
     * @param array $createStatus
     *
     * @return bool
     */
    public function afterCreate($createStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $createStatus;
        $this->trigger(
            self::EVENT_AFTER_CREATE,
            $event
        );
    }

    /**
     * Generates class base name and namespace from migration name from user input.
     * @param string $name migration name from user input.
     * @return array list of 2 elements: 'namespace' and 'class base name'
     * @since 2.0.10
     */
    private function generateClassName($name)
    {
        $namespace = null;
        $name = trim($name, '\\');
        if (strpos($name, '\\') !== false) {
            $namespace = substr($name, 0, strrpos($name, '\\'));
            $name = substr($name, strrpos($name, '\\') + 1);
        } else {
            if ($this->migrationPath === null) {
                $migrationNamespaces = $this->migrationNamespaces;
                $namespace = array_shift($migrationNamespaces);
            }
        }

        if ($namespace === null) {
            $class = 'm' . gmdate('ymd_His') . '_' . $name;
        } else {
            $class = 'M' . gmdate('ymdHis') . ucfirst($name);
        }

        return [$namespace, $class];
    }

    /**
     * Finds the file path for the specified migration namespace.
     * @param string|null $namespace migration namespace.
     * @return string migration file path.
     * @throws Exception on failure.
     * @since 2.0.10
     */
    private function findMigrationPath($namespace)
    {
        if (empty($namespace)) {
            return $this->migrationPath;
        }

        if (!in_array($namespace, $this->migrationNamespaces, true)) {
            throw new Exception("Namespace '{$namespace}' not found in `migrationNamespaces`");
        }

        return $this->getNamespacePath($namespace);
    }

    /**
     * Returns the file path matching the give namespace.
     * @param string $namespace namespace.
     * @return string file path.
     * @since 2.0.10
     */
    private function getNamespacePath($namespace)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, Yii::getAlias('@' . str_replace('\\', '/', $namespace)));
    }

    /**
     * Upgrades with the specified migration class.
     * @param string $class the migration class name
     * @return boolean whether the migration is successful
     */
    protected function migrateUp($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }

        $success = false;
        $migrateUpgradeStatus = [
            'start' => microtime(true)
        ];
        if ($this->beforeMigrateUpgrade($migrateUpgradeStatus)) {
            $migration = $this->createMigration($class);
            if ($migration->up() !== false) {
                $this->addMigrationHistory($class);
                $success = true;
            }
            $migrateUpgradeStatus['success'] = $success;
            $migrateUpgradeStatus['end'] = microtime(true);
            $this->afterMigrateUpgrade($migrateUpgradeStatus);
        }

        return $success;
    }

    /**
     * Downgrades with the specified migration class.
     * @param string $class the migration class name
     * @return boolean whether the migration is successful
     */
    protected function migrateDown($class)
    {
        if ($class === self::BASE_MIGRATION) {
            return true;
        }

        $success = false;
        $migrateDowngradeStatus = [
            'start' => microtime(true)
        ];
        if ($this->beforeMigrateDowngrade($migrateDowngradeStatus)) {
            $migration = $this->createMigration($class);
            if ($migration->down() !== false) {
                $this->removeMigrationHistory($class);
                $success = true;
            }
            $migrateDowngradeStatus['success'] = $success;
            $migrateDowngradeStatus['end'] = microtime(true);
            $this->afterMigrateDowngrade($migrateDowngradeStatus);
        }

        return $success;
    }

    /**
     * Prepares class name and requests inclusion of migration class file
     * @param string $class the migration class name
     */
    protected function prepareMigrationClass(&$class)
    {
        $class = trim($class, '\\');
        if (strpos($class, '\\') === false) {
            $file = $this->migrationPath . DIRECTORY_SEPARATOR . $class . '.php';

            /** @noinspection PhpIncludeInspection */
            require_once($file);
        }
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\db\MigrationInterface the migration instance
     */
    protected function createMigration($class)
    {
        $this->prepareMigrationClass($class);

        return new $class();
    }

    /**
     * Migrates to the specified apply time in the past.
     *
     * @param integer $time UNIX timestamp value.
     *
     * @return bool|null
     */
    protected function migrateToTime($time)
    {
        $count = 0;
        $migrations = array_values($this->getMigrationHistory(null));
        while ($count < count($migrations) && $migrations[$count] > $time) {
            ++$count;
        }
        if ($count === 0) {
            return null;
        } else {
            return $this->downgrade($count);
        }
    }

    /**
     * Migrates to the certain version.
     * @param string $version name in the full format.
     * @return integer CLI exit code
     * @throws Exception if the provided version cannot be found.
     */
    protected function migrateToVersion($version)
    {
        $originalVersion = $version;

        // try migrate up
        $migrations = $this->getNewMigrations();
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {

                return $this->upgrade($i + 1);
            }
        }

        // try migrate down
        $migrations = array_keys($this->getMigrationHistory(null));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                if ($i === 0) {
                    return null;
                } else {
                    return $this->downgrade($i);
                }
            }
        }

        throw new Exception("Unable to find the version '$originalVersion'.");
    }

    /**
     * Returns the migrations that are not applied.
     * @return array list of new migrations
     */
    protected function getNewMigrations()
    {
        $applied = [];
        foreach ($this->getMigrationHistory(null) as $class => $time) {
            $applied[trim($class, '\\')] = true;
        }

        $migrationPaths = [];
        if (!empty($this->migrationPath)) {
            $migrationPaths[''] = $this->migrationPath;
        }
        foreach ($this->migrationNamespaces as $namespace) {
            $migrationPaths[$namespace] = $this->getNamespacePath($namespace);
        }

        $migrations = [];
        foreach ($migrationPaths as $namespace => $migrationPath) {
            if (!file_exists($migrationPath)) {
                continue;
            }
            $handle = opendir($migrationPath);
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $migrationPath . DIRECTORY_SEPARATOR . $file;
                if (preg_match('/^(m(\d{6}_?\d{6})\D.*?)\.php$/is', $file, $matches) && is_file($path)) {
                    $class = $matches[1];
                    if (!empty($namespace)) {
                        $class = $namespace . '\\' . $class;
                    }
                    $time = str_replace('_', '', $matches[2]);
                    if (!isset($applied[$class])) {
                        $migrations[$time . '\\' . $class] = $class;
                    }
                }
            }
            closedir($handle);
        }
        ksort($migrations);

        return array_values($migrations);
    }

    /**
     * Returns the view object that can be used to render views or view files.
     * The [[render()]], [[renderPartial()]] and [[renderFile()]] methods will use
     * this view object to implement the actual view rendering.
     * If not set, it will default to the "view" application component.
     * @return View|\yii\web\View the view object that can be used to render views or view files.
     */
    public function getView()
    {
        if ($this->_view === null) {
            $this->_view = Yii::$app->getView();
        }
        return $this->_view;
    }

    /**
     * Sets the view object to be used by this component.
     * @param View|\yii\web\View $view the view object that can be used to render views or view files.
     */
    public function setView($view)
    {
        $this->_view = $view;
    }

    /**
     * Renders a view file.
     * @param string $file the view file to be rendered. This can be either a file path or a path alias.
     * @param array $params the parameters (name-value pairs) that should be made available in the view.
     * @return string the rendering result.
     * @throws InvalidParamException if the view file does not exist.
     */
    protected function renderFile($file, $params = [])
    {
        return $this->getView()->renderFile($file, $params, $this);
    }

    /**
     * Generates new migration source PHP code.
     * Child class may override this method, adding extra logic or variation to the process.
     * @param array $params generation parameters, usually following parameters are present:
     *
     *  - name: string migration base name
     *  - className: string migration class name
     *
     * @return string generated PHP code.
     * @since 2.0.8
     */
    protected function generateMigrationSourceCode($params)
    {
        return $this->renderFile(Yii::getAlias($this->templateFile), $params);
    }

    /**
     * Checks if given migration version specification matches namespaced migration name.
     * @param string $rawVersion raw version specification received from user input.
     * @return string|false actual migration version, `false` - if not match.
     * @since 2.0.10
     */
    private function extractNamespaceMigrationVersion($rawVersion)
    {
        if (preg_match('/^\\\\?([\w_]+\\\\)+m(\d{6}_?\d{6})(\D.*)?$/is', $rawVersion, $matches)) {
            return trim($rawVersion, '\\');
        }
        return false;
    }

    /**
     * Checks if given migration version specification matches migration base name.
     * @param string $rawVersion raw version specification received from user input.
     * @return string|false actual migration version, `false` - if not match.
     * @since 2.0.10
     */
    private function extractMigrationVersion($rawVersion)
    {
        if (preg_match('/^m?(\d{6}_?\d{6})(\D.*)?$/is', $rawVersion, $matches)) {
            return 'm' . $matches[1];
        }
        return false;
    }

    /**
     * Returns the migration history.
     * @param integer $limit the maximum number of records in the history to be returned. `null` for "no limit".
     * @return array the migration history
     */
    abstract protected function getMigrationHistory($limit);

    /**
     * Adds new migration entry to the history.
     * @param string $version migration version name.
     */
    abstract protected function addMigrationHistory($version);

    /**
     * Removes existing migration from the history.
     * @param string $version migration version name.
     */
    abstract protected function removeMigrationHistory($version);

    /**
     * This method is invoked right before action is to be executed
     * It checks the existence of the [[migrationPath]].
     * @param bool|false $create
     * @throws InvalidConfigException if directory specified in migrationPath doesn't exist and "create" if false.
     */
    public function ensureMigrationLocations($create = false) {
        if (empty($this->migrationNamespaces) && empty($this->migrationPath)) {
            throw new InvalidConfigException("Either migrationNamespaces or migrationPath should be defined");
        }

        foreach ($this->migrationNamespaces as $key => $value) {
            $this->migrationNamespaces[$key] = trim($value, '\\');
        }

        if ($this->migrationPath !== null) {
            $path = Yii::getAlias($this->migrationPath);
            if (!is_dir($path)) {
                if (!$create) {
                    throw new InvalidConfigException("Directory specified in migrationPath doesn't exist: {$this->migrationPath}");
                }
                FileHelper::createDirectory($path);
            }
            $this->migrationPath = $path;
        }
    }

    /**
     * Upgrades the application by applying new migrations.
     * For example,
     *
     * ```
     * BaseMigrator::upgrade()  # apply all new migrations
     * BaseMigrator::upgrade(3) # apply the first 3 new migrations
     * ```
     *
     * @param integer $limit the number of new migrations to be applied. If 0, it means
     * applying all available new migrations.
     *
     * @return null|bool the status of the upgrade. null means no new migrations, bool means whether new migrations were applied.
     */
    public function upgrade($limit = 0)
    {
        $this->ensureMigrationLocations();
        $migrations = $this->getNewMigrations();
        if (empty($migrations)) {
            // No new migrations found. Your system is up-to-date.

            return null;
        }

        $limit = (int) $limit;
        $upgradeStatus = [
            'limit' => $limit,
            'totalCount' => count($migrations),
            'migrations' => $migrations
        ];
        if ($limit > 0) {
            $migrations = array_slice($migrations, 0, $limit);
        }

        $upgradeStatus['count'] = count($migrations);
        if ($this->beforeUpgrade($upgradeStatus)) {
            $success = true;
            $applied = 0;
            foreach ($migrations as $migration) {
                if ($this->migrateUp($migration)) {
                    $applied++;
                }
                else {
                    // Migration failed. The rest of the migrations are canceled.
                    $success = false;
                    break;
                }
            }
            $upgradeStatus['applied'] = $applied;
            $upgradeStatus['success'] = $success;
            $this->afterUpgrade($upgradeStatus);

            return $success;
        }

        return false;
    }

    /**
     * Downgrades the application by reverting old migrations.
     * For example,
     *
     * ```
     * BaseMigrator::downgrade()     # revert the last migration
     * BaseMigrator::downgrade(3)    # revert the last 3 migrations
     * BaseMigrator::downgrade(null) # revert all migrations
     * ```
     *
     * @param integer $limit the number of migrations to be reverted. Defaults to 1,
     * meaning the last applied migration will be reverted.
     *
     * @return null|bool the status of the downgrade. null means no new migrations, bool means whether migrations were reverted.
     */
    public function downgrade($limit = 1)
    {
        $this->ensureMigrationLocations();

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations)) {
            // No migration has been done before.

            return null;
        }

        $migrations = array_keys($migrations);

        $downgradeStatus = [
            'limit' => $limit,
            'totalCount' => count($migrations),
            'migrations' => $migrations
        ];
        if ($this->beforeDowngrade($downgradeStatus)) {
            $success = true;
            $reverted = 0;
            foreach ($migrations as $migration) {
                if ($this->migrateDown($migration)) {
                    $reverted++;
                }
                else {
                    // Migration failed. The rest of the migrations are canceled.
                    $success = false;
                    break;
                }
            }
            $upgradeStatus['reverted'] = $reverted;
            $upgradeStatus['success'] = $success;
            $this->afterUpgrade($downgradeStatus);

            return $success;
        }

        return false;
    }

    /**
     * Redoes the last few migrations.
     *
     * This command will first revert the specified migrations, and then apply
     * them again. For example,
     *
     * ```
     * BaseMigrator::redo()     # redo the last applied migration
     * BaseMigrator::redo(3)    # redo the last 3 applied migrations
     * BaseMigrator::redo(null) # redo all migrations
     * ```
     *
     * @param integer $limit the number of migrations to be redone. Defaults to 1,
     * meaning the last applied migration will be redone.
     *
     * @return null|bool the status of the redo. null means no migration has been done before, bool means whether redo was successful.
     */
    public function redo($limit = 1)
    {
        $this->ensureMigrationLocations();

        $migrations = $this->getMigrationHistory($limit);

        if (empty($migrations)) {
            // No migration has been done before.

            return null;
        }

        $migrations = array_keys($migrations);

        $redoStatus = [
            'limit' => $limit,
            'totalCount' => count($migrations),
            'migrations' => $migrations
        ];
        if ($this->beforeRedo($redoStatus)) {
            $success = true;
            $reverted = 0;
            foreach ($migrations as $migration) {
                if ($this->migrateDown($migration)) {
                    $reverted++;
                }
                else {
                    // Migration failed. The rest of the migrations are canceled.
                    $success = false;
                    break;
                }
            }
            $upgradeStatus['reverted'] = $reverted;
            $applied = 0;
            if ($success) {
                foreach (array_reverse($migrations) as $migration) {
                    if ($this->migrateUp($migration)) {
                        $applied++;
                    }
                    else {
                        // Migration failed. The rest of the migrations are canceled.
                        $success = false;
                        break;
                    }
                }
            }
            $upgradeStatus['applied'] = $applied;
            $redoStatus['success'] = $success;
            $this->afterRedo($redoStatus);

            return $success;
        }

        return false;
    }

    /**
     * Parses version string to determine what type is it
     *
     * @param string $version
     * @throws InvalidParamException if the version argument is invalid.
     * @return array
     */
    public function parseVersionString($version)
    {
        if (($namespaceVersion = $this->extractNamespaceMigrationVersion($version)) !== false) {
            return ['namespace', $namespaceVersion];
        } elseif (($migrationName = $this->extractMigrationVersion($version)) !== false) {
            return ['name', $migrationName];
        } elseif ((string) (int) $version == $version) {
            return ['timestamp', $version];
        } elseif (($time = strtotime($version)) !== false) {
            return ['time', $time];
        } else {
            throw new InvalidParamException('Invalid version');
        }
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
     * BaseMigrator::to('101129_185401')                          # using timestamp
     * BaseMigrator::to('m101129_185401_create_user_table')       # using full name
     * BaseMigrator::to('1392853618')                             # using UNIX timestamp
     * BaseMigrator::to('2014-02-15 13:00:50')                    # using strtotime() parseable string
     * BaseMigrator::to('app\migrations\M101129185401CreateUser') # using full namespace name
     * ```
     *
     * @param string $version either the version name or the certain time value in the past
     * that the application should be migrated to. This can be either the timestamp,
     * the full name of the migration, the UNIX timestamp, or the parseable datetime
     * string.
     * @return null|bool the status of the action. null means that there was nothing to do, bool means whether migrations was successful.
     */
    public function to($version)
    {
        $this->ensureMigrationLocations();
        list($type, $value) = $this->parseVersionString($version);
        switch ($type) {
            case 'namespace':
            case 'name':
                return $this->migrateToVersion($value);
                break;
            case 'timestamp':
            case 'time':
                return $this->migrateToTime($value);
                break;
            default:
                return null;
        }
    }

    /**
     * Modifies the migration history to the specified version.
     *
     * No actual migration will be performed.
     *
     * ```
     * BaseMigrator::mark('101129_185401')                          # using timestamp
     * BaseMigrator::mark('m101129_185401_create_user_table')       # using full name
     * BaseMigrator::mark('app\migrations\M101129185401CreateUser') # using full namespace name
     * ```
     *
     * @param string $version the version at which the migration history should be marked.
     * This can be either the timestamp or the full name of the migration.
     * @throws InvalidParamException if the version argument is invalid or the version cannot be found.
     * @return null|bool the status of the mark. null means that already at version, bool means whether mark was successful.
     */
    public function mark($version)
    {
        $this->ensureMigrationLocations();
        $originalVersion = $version;
        $version = $this->parseVersionString($version)[1];
        $markStatus = [
            'originalVersion' => $originalVersion
        ];

        // try mark up
        $migrations = $this->getNewMigrations();
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                if ($this->beforeMark($markStatus)) {
                    for ($j = 0; $j <= $i; ++$j) {
                        $this->addMigrationHistory($migrations[$j]);
                    }
                    $this->afterMark($markStatus);
                }

                return true;
            }
        }

        // try mark down
        $migrations = array_keys($this->getMigrationHistory(null));
        foreach ($migrations as $i => $migration) {
            if (strpos($migration, $version) === 0) {
                if ($i === 0) {
                    // Already at originalVersion. Nothing needs to be done

                    return null;
                } else {
                    if ($this->beforeMark($markStatus)) {
                        for ($j = 0; $j <= $i; ++$j) {
                            $this->removeMigrationHistory($migrations[$j]);
                        }
                        $this->afterMark($markStatus);
                    }
                }

                return true;
            }
        }

        // Unable to find the version originalVersion
        return false;
    }

    /**
     * Returns the migration history.
     *
     * This command will return the list of migrations that have been applied
     * so far. For example,
     *
     * ```
     * BaseMigrator::history()      # returning the last 10 migrations
     * BaseMigrator::history(5)     # returning the last 5 migrations
     * BaseMigrator::history(null)  # returning the whole history
     * ```
     *
     * @param integer $limit the maximum number of migrations to be displayed.
     * If it is "all", the whole migration history will be displayed.
     *
     * @return array
     */
    public function history($limit = 10)
    {
        $this->ensureMigrationLocations();
        return $this->getMigrationHistory($limit);
    }

    /**
     * Returns the un-applied new migrations.
     *
     * This command will return the new migrations that have not been applied.
     */
    public function newMigrations()
    {
        $this->ensureMigrationLocations();
        return $this->getNewMigrations();
    }

    /**
     * Creates a new migration.
     *
     * This method creates a new migration using the available migration template.
     * After using this command, developers should modify the created migration
     * skeleton by filling up the actual migration logic.
     *
     * ```
     * BaseMigrator::create('create_user_table')
     * ```
     *
     * In order to generate a namespaced migration, you should specify a namespace before the migration's name.
     * Note that backslash (`\`) is usually considered a special character in the shell, so you need to escape it
     * properly to avoid shell errors or incorrect behavior.
     * For example:
     *
     * ```
     * BaseMigrator::create('app\\migrations\\createUserTable')
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
    public function create($name)
    {
        $this->ensureMigrationLocations(true);
        if (!preg_match('/^[\w\\\\]+$/', $name)) {
            throw new InvalidParamException('Invalid name.');
        }

        list($namespace, $className) = $this->generateClassName($name);
        $migrationPath = $this->findMigrationPath($namespace);

        $file = $migrationPath . DIRECTORY_SEPARATOR . $className . '.php';
        $createStatus = [
            'file' => $file,
            'name' => $name,
            'className' => $className,
            'namespace' => $namespace,
        ];
        if ($this->beforeCreate($createStatus)) {
            $content = $this->generateMigrationSourceCode([
                'name' => $name,
                'className' => $className,
                'namespace' => $namespace,
            ]);
            FileHelper::createDirectory($migrationPath);
            file_put_contents($file, $content);
            $this->afterCreate($createStatus);
        }
    }
}
