<?php
namespace yii\base;

use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\ArrayHelper;

/**
 * Manages application migrations.
 *
 * @author Deele <deele@tuta.io>
 */
class Migrator extends BaseMigrator
{
    const EVENT_BEFORE_CREATE_HISTORY_TABLE = 'beforeCreateHistoryTable';
    const EVENT_AFTER_CREATE_HISTORY_TABLE = 'afterCreateHistoryTable';

    /**
     * @var string the name of the table for keeping applied migration information.
     */
    public $migrationTable = '{{%migration}}';
    /**
     * @inheritdoc
     */
    public $templateFile = '@yii/views/migration.php';
    /**
     * @var array a set of template paths for generating migration code automatically.
     *
     * The key is the template type, the value is a path or the alias. Supported types are:
     * - `create_table`: table creating template
     * - `drop_table`: table dropping template
     * - `add_column`: adding new column template
     * - `drop_column`: dropping column template
     * - `create_junction`: create junction template
     *
     * @since 2.0.7
     */
    public $generatorTemplateFiles = [
        'create_table' => '@yii/views/createTableMigration.php',
        'drop_table' => '@yii/views/dropTableMigration.php',
        'add_column' => '@yii/views/addColumnMigration.php',
        'drop_column' => '@yii/views/dropColumnMigration.php',
        'create_junction' => '@yii/views/createTableMigration.php',
    ];
    /**
     * @var boolean indicates whether the table names generated should consider
     * the `tablePrefix` setting of the DB connection. For example, if the table
     * name is `post` the generator wil return `{{%post}}`.
     * @since 2.0.8
     */
    public $useTablePrefix = false;
    /**
     * @var array column definition strings used for creating migration code.
     *
     * The format of each definition is `COLUMN_NAME:COLUMN_TYPE:COLUMN_DECORATOR`. Delimiter is `,`.
     * For example, `--fields="name:string(12):notNull:unique"`
     * produces a string column of size 12 which is not null and unique values.
     *
     * Note: primary key is added automatically and is named id by default.
     * If you want to use another name you may specify it explicitly like
     * `--fields="id_key:primaryKey,name:string(12):notNull:unique"`
     * @since 2.0.7
     */
    public $fields = [];
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection to use
     * when applying migrations. Starting from version 2.0.3, this can also be a configuration array
     * for creating the object.
     */
    public $db = 'db';

    /**
     * This method is invoked before history table creation starts.
     *
     * @param array $upgradeStatus
     *
     * @return bool
     */
    public function beforeCreateHistoryTable($upgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $upgradeStatus;
        $event->contextData['createHistoryTable'] = true;
        $this->trigger(
            self::EVENT_BEFORE_CREATE_HISTORY_TABLE,
            $event
        );
        return $event->contextData['createHistoryTable'];
    }

    /**
     * This method is invoked after history table creation finished.
     *
     * @param array $upgradeStatus
     *
     * @return bool
     */
    public function afterCreateHistoryTable($upgradeStatus = [])
    {
        $event = new RichEvent();
        $event->contextData = $upgradeStatus;
        $this->trigger(
            self::EVENT_AFTER_CREATE_HISTORY_TABLE,
            $event
        );
    }

    /**
     * Ensures database connection is on
     *
     * @inheritdoc
     */
    public function ensureMigrationLocations($create = false) {
        parent::ensureMigrationLocations($create);
        if (!$create) {
            $this->db = Instance::ensure($this->db, Connection::className());
        }
    }

    /**
     * Creates a new migration instance.
     * @param string $class the migration class name
     * @return \yii\db\Migration the migration instance
     */
    protected function createMigration($class)
    {
        $this->prepareMigrationClass($class);

        return new $class(['db' => $this->db]);
    }

    /**
     * @inheritdoc
     */
    protected function getMigrationHistory($limit)
    {
        if ($this->db->schema->getTableSchema($this->migrationTable, true) === null) {
            $this->createMigrationHistoryTable();
        }
        $query = new Query;
        $rows = $query->select(['version', 'apply_time'])
                      ->from($this->migrationTable)
                      ->orderBy('apply_time DESC, version DESC')
                      ->limit($limit)
                      ->createCommand($this->db)
                      ->queryAll();
        $history = ArrayHelper::map($rows, 'version', 'apply_time');
        unset($history[self::BASE_MIGRATION]);

        return $history;
    }

    /**
     * Creates the migration history table.
     */
    protected function createMigrationHistoryTable()
    {
        $tableName = $this->db->schema->getRawTableName($this->migrationTable);
        $createHistoryTableStatus = [
            'tableName' => $tableName
        ];
        if ($this->beforeCreateHistoryTable($createHistoryTableStatus)) {

            /**
             * @var \yii\db\Command $command
             */
            $command = $this->db->createCommand();
            $command->createTable($this->migrationTable, [
                'version' => 'varchar(180) NOT NULL PRIMARY KEY',
                'apply_time' => 'integer',
            ])->execute();

            /**
             * @var \yii\db\Command $command
             */
            $command = $this->db->createCommand();
            $command->insert($this->migrationTable, [
                'version' => self::BASE_MIGRATION,
                'apply_time' => time(),
            ])->execute();
            $this->afterCreateHistoryTable($createHistoryTableStatus);
        }
    }

    /**
     * @inheritdoc
     */
    protected function addMigrationHistory($version)
    {
        $command = $this->db->createCommand();

        /**
         * @var \yii\db\Command $command
         */
        $command->insert($this->migrationTable, [
            'version' => $version,
            'apply_time' => time(),
        ])->execute();
    }

    /**
     * @inheritdoc
     */
    protected function removeMigrationHistory($version)
    {
        $command = $this->db->createCommand();

        /**
         * @var \yii\db\Command $command
         */
        $command->delete($this->migrationTable, [
            'version' => $version,
        ])->execute();
    }

    /**
     * @inheritdoc
     * @since 2.0.8
     */
    protected function generateMigrationSourceCode($params)
    {
        $parsedFields = $this->parseFields();
        $fields = $parsedFields['fields'];
        $foreignKeys = $parsedFields['foreignKeys'];

        $name = $params['name'];

        $templateFile = $this->templateFile;
        $table = null;
        if (preg_match('/^create_junction(?:_table_for_|_for_|_)(.+)_and_(.+)_tables?$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['create_junction'];
            $firstTable = mb_strtolower($matches[1], Yii::$app->charset);
            $secondTable = mb_strtolower($matches[2], Yii::$app->charset);

            $fields = array_merge(
                [
                    [
                        'property' => $firstTable . '_id',
                        'decorators' => 'integer()',
                    ],
                    [
                        'property' => $secondTable . '_id',
                        'decorators' => 'integer()',
                    ],
                ],
                $fields,
                [
                    [
                        'property' => 'PRIMARY KEY(' .
                            $firstTable . '_id, ' .
                            $secondTable . '_id)',
                    ],
                ]
            );

            $foreignKeys[$firstTable . '_id'] = $firstTable;
            $foreignKeys[$secondTable . '_id'] = $secondTable;
            $table = $firstTable . '_' . $secondTable;
        } elseif (preg_match('/^add_(.+)_columns?_to_(.+)_table$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['add_column'];
            $table = mb_strtolower($matches[2], Yii::$app->charset);
        } elseif (preg_match('/^drop_(.+)_columns?_from_(.+)_table$/', $name, $matches)) {
            $templateFile = $this->generatorTemplateFiles['drop_column'];
            $table = mb_strtolower($matches[2], Yii::$app->charset);
        } elseif (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            $this->addDefaultPrimaryKey($fields);
            $templateFile = $this->generatorTemplateFiles['create_table'];
            $table = mb_strtolower($matches[1], Yii::$app->charset);
        } elseif (preg_match('/^drop_(.+)_table$/', $name, $matches)) {
            $this->addDefaultPrimaryKey($fields);
            $templateFile = $this->generatorTemplateFiles['drop_table'];
            $table = mb_strtolower($matches[1], Yii::$app->charset);
        }

        foreach ($foreignKeys as $column => $relatedTable) {
            $foreignKeys[$column] = [
                'idx' => $this->generateTableName("idx-$table-$column"),
                'fk' => $this->generateTableName("fk-$table-$column"),
                'relatedTable' => $this->generateTableName($relatedTable),
            ];
        }

        return $this->renderFile(Yii::getAlias($templateFile), array_merge($params, [
            'table' => $this->generateTableName($table),
            'fields' => $fields,
            'foreignKeys' => $foreignKeys,
        ]));
    }

    /**
     * If `useTablePrefix` equals true, then the table name will contain the
     * prefix format.
     *
     * @param string $tableName the table name to generate.
     * @return string
     * @since 2.0.8
     */
    protected function generateTableName($tableName)
    {
        if (!$this->useTablePrefix) {
            return $tableName;
        }
        return '{{%' . $tableName . '}}';
    }

    /**
     * Parse the command line migration fields
     * @return array parse result with following fields:
     *
     * - fields: array, parsed fields
     * - foreignKeys: array, detected foreign keys
     *
     * @since 2.0.7
     */
    protected function parseFields()
    {
        $fields = [];
        $foreignKeys = [];

        foreach ($this->fields as $index => $field) {
            $chunks = preg_split('/\s?:\s?/', $field, null);
            $property = array_shift($chunks);

            foreach ($chunks as $i => &$chunk) {
                if (strpos($chunk, 'foreignKey') === 0) {
                    preg_match('/foreignKey\((\w*)\)/', $chunk, $matches);
                    $foreignKeys[$property] = isset($matches[1])
                        ? $matches[1]
                        : preg_replace('/_id$/', '', $property);

                    unset($chunks[$i]);
                    continue;
                }

                if (!preg_match('/^(.+?)\(([^(]+)\)$/', $chunk)) {
                    $chunk .= '()';
                }
            }
            $fields[] = [
                'property' => $property,
                'decorators' => implode('->', $chunks),
            ];
        }

        return [
            'fields' => $fields,
            'foreignKeys' => $foreignKeys,
        ];
    }

    /**
     * Adds default primary key to fields list if there's no primary key specified
     * @param array $fields parsed fields
     * @since 2.0.7
     */
    protected function addDefaultPrimaryKey(&$fields)
    {
        foreach ($fields as $field) {
            if ($field['decorators'] === 'primaryKey()' || $field['decorators'] === 'bigPrimaryKey()') {
                return;
            }
        }
        array_unshift($fields, ['property' => 'id', 'decorators' => 'primaryKey()']);
    }
}
