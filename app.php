<?php

/**  design patterns used in this file:
 *      - singleton
 *      - command
 */

// ---------------- Helpers Start ----------------
if (!function_exists('secondsToTime')) {
    /**
     * Convert seconds to DD:HH:MM
     */
    function secondsToTime($seconds)
    {
        $from = new \DateTime('@0');
        $to = new \DateTime("@$seconds");
        return $from->diff($to)->format('%D:%H:%i');
    }
}

if (!function_exists('dd')) {
    /**
     * Convert seconds to DD:HH:MM
     */
    function dd(...$vars)
    {
        foreach ($vars as $var) {
            var_dump($var);
        }

        die;
    }
}
// ---------------- Helpers End ----------------

// ---------------- Contracts Start ----------------
interface Command
{
    public function perform();
}

abstract class Action implements Command
{
}

abstract class Model
{
    private const CITY = 1;
    private const ROAD = 2;

    private const CITY_LABEL = 'City';
    private const ROAD_LABEL = 'Road';

    public const MODELS = [
        self::CITY => self::CITY_LABEL,
        self::ROAD => self::ROAD_LABEL,
    ];

    public const CLASS_MAP = [
        self::CITY => City::class,
        self::ROAD => Road::class,
    ];

    // our database is a simple array because we don't need any persistence and complex queries.
    private array $db = [];

    /**
     * The Singleton's instance is stored in a static field. This field is an
     * array, because we'll allow our Singleton to have subclasses. Each item in
     * this array will be an instance of a specific Singleton's subclass. You'll
     * see how this works in a moment.
     */
    private static $instances = [];

    /**
     * The Singleton's constructor should always be private to prevent direct
     * construction calls with the `new` operator.
     */
    private function __construct()
    {
    }

    /**
     * Singletons should not be cloneable.
     */
    protected function __clone()
    {
    }

    /**
     * Singletons should not be restorable from strings.
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * This is the static method that controls the access to the singleton
     * instance. On the first run, it creates a singleton object and places it
     * into the static field. On subsequent runs, it returns the client existing
     * object stored in the static field.
     *
     * This implementation lets you subclass the Singleton class while keeping
     * just one instance of each subclass around.
     */
    public static function getInstance(): self
    {
        $subclass = static::class;
        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new static();
        }

        return self::$instances[$subclass];
    }

    public function getDb(): array
    {
        return $this->db[$this->collection];
    }

    public function setDb(array $data): self
    {
        $this->db[$this->collection] = $data;
        return $this;
    }

    protected abstract function fill();

    public static function chooseModel()
    {
        echo "Select model:" . PHP_EOL;
        foreach (static::MODELS as $key => $value) {
            echo $key . '. ' . $value . PHP_EOL;
        }

        return (int) trim(readline());
    }

    public function create()
    {
        $data = $this->fill();
        echo <<<EOT
            {$this->modelName} with id={$data['id']} added!
            Select your next action
            1. Add another {$this->modelName}
            2. Main Menu
            EOT . PHP_EOL;
        $choice = (int) trim(readline());

        $this->db[$this->collection][$data['id']] = $data;
        if ($choice == 2) {
            return;
        }

        $this->create();
    }

    public function delete()
    {
        $id = (int) trim(readline());
        if (!isset($this->db[$this->collection][$id])) {
            echo "{$this->modelName} with id {$id} not found!" . PHP_EOL;
            return;
        }
        unset($this->db[$this->collection][$id]);
        echo "{$this->modelName}:{$id} deleted!" . PHP_EOL;
    }
}
// ---------------- Contracts End ----------------

// ---------------- Data Access Layer Start ----------------
// ----------------------------------------------------
// Like laravel and MVC Architecture, we use model to interacte with database.
// ----------------------------------------------------
class City extends Model
{
    public $collection = 'cities';

    protected $modelName = __CLASS__;

    protected $schema = [
        'id' => 0,
        'name' => '',
    ];

    protected function fill()
    {
        foreach (array_keys($this->schema) as $value) {
            echo "{$value}=?" . PHP_EOL;
            $this->schema[$value] = trim(readline());
        }

        return $this->schema;
    }
}

class Road extends Model
{
    public $collection = 'roads';

    protected $modelName = __CLASS__;

    protected $schema = [
        'id' => 0,
        'name' => '',
        'from' => 0,
        'to' => 0,
        'through' => [],
        'speed_limit' => 0,
        'length' => 0,
        'bi_directional' => false,
    ];

    protected function fill()
    {
        foreach (array_keys($this->schema) as $value) {
            if ($value == 'through') {
                echo "through=?" . PHP_EOL;
                $this->schema['through'] = explode(',', str_replace(['[', ']', ' '], '', trim(readline())));
            } else {
                echo "{$value}=?" . PHP_EOL;
                $this->schema[$value] = trim(readline());
            }
        }

        return $this->schema;
    }
}
// ---------------- Data Access Layer End ----------------

// ---------------- Actions Layer Start ----------------
// --------------------------------------------------
// Actions are like commands in the Command Pattern
// --------------------------------------------------
class AddAction extends Action
{
    public function perform()
    {
        $inputModel = Model::chooseModel();

        if (array_key_exists($inputModel, Model::MODELS)) {
            $class = Model::CLASS_MAP[$inputModel];
            $class::getInstance()->create();
        } else {
            $this->perform();
        }
    }
}

class DeleteAction extends Action
{
    public function perform()
    {
        $inputModel = Model::chooseModel();
        if (array_key_exists($inputModel, Model::MODELS)) {
            $class = Model::CLASS_MAP[$inputModel];
            $class::getInstance()->delete();
        } else {
            $this->perform();
        }
    }
}

class PathAction extends Action
{
    /**
     * iterates on all roads and print all pathes that between two cities
     */
    public function perform()
    {
        $sourceAndDestination = explode(':', trim(readline()));
        $source_id = (int) $sourceAndDestination[0];
        $destination_id = (int) $sourceAndDestination[1];
        $cities = City::getInstance()->getDb();

        if (!isset($cities[$source_id]) || !isset($cities[$destination_id])) {
            $this->perform();
        }

        $paths = $this->findPaths($source_id, $destination_id);

        foreach ($paths as $path) {
            // here we should divide road length by speed limit and convert it to 3600 (1 hour -> 3600 seconds)
            $time = secondsToTime(($path['length'] / $path['speed_limit']) * 3600);
            echo <<<EOT
            {$cities[$source_id]['name']}:{$cities[$destination_id]['name']} via Road {$path['name']}: Takes {$time}
            EOT . PHP_EOL;
        }
    }

    /**
     * here we merge all cities in the road and check if source and destination are in the road
     * and then add it to the paths array
     */
    protected function findPaths($source_id, $destination_id)
    {
        $roads = Road::getInstance()->getDb();

        $paths = [];
        foreach ($roads as $road) {
            // dest: 1
            // source : 2
            // through :[1] + [2 ,3, 4] + [2] = [1, 2, 3, 4]
            $road['through'] = array_unique(array_merge([$road['from']], $road['through'], [$road['to']]));
            $paths[] = $this->checkRoad($road, $source_id, $destination_id, $road['bi_directional']);
        }
        return array_filter($paths);
    }

    /**
     * handles the direction of the road and perform search
     * if road is not bi-directional, the source city index is always less than the destination city index
     */
    protected function checkRoad($road, $source_id, $destination_id, $biDirectional)
    {
        if (!in_array($source_id, $road['through']) || !in_array($destination_id, $road['through'])) {
            return;
        }

        if (!$biDirectional) {
            $sourceIndex = array_search($source_id, $road['through']);
            $destinationIndex = array_search($destination_id, $road['through']);
            return ($sourceIndex < $destinationIndex) ? $road : null;
        }

        return $road;
    }
}

class HelpAction extends Action
{
    public function perform()
    {
        echo "Select a number from shown menu and enter. For example 1 is for help." . PHP_EOL;
    }
}


class ExitAction extends Action
{
    public function perform()
    {
        exit;
    }
}

class DatabaseAction extends Action
{
    protected const SAVE_OPTION_LABEL = 'Save';
    protected const LOAD_OPTION_LABEL = 'Load';

    protected const SAVE_OPTION = 1;
    protected const LOAD_OPTION = 2;

    protected $options = [
        self::SAVE_OPTION => self::SAVE_OPTION_LABEL,
        self::LOAD_OPTION => self::LOAD_OPTION_LABEL,
    ];

    private $filePath = 'db.json';

    public function perform()
    {
        echo "choose your action: " . PHP_EOL;
        foreach ($this->options as $key => $value) {
            echo "{$key} - {$value}" . PHP_EOL;
        }

        $option = (int) trim(readline());

        if (! array_key_exists($option , $this->options)) {
            echo "your choose is invalid !" . PHP_EOL;
            $this->perform();
        }

        $method = 'handle' . $this->options[$option];
        $this->$method();
    }

    protected function handleLoad()
    {
        $database = json_decode(file_get_contents($this->filePath), true);
        $models = array_values(Model::CLASS_MAP);

        foreach($models as $model) {
            $modelObject =  $model::getInstance();
            $modelObject->setDb($database[$modelObject->collection]);
        }
    }

    protected function handleSave()
    {   
        $models = array_values(Model::CLASS_MAP);
        $db = [];
        foreach($models as $model) {
            $modelObject =  $model::getInstance();
            $db[$modelObject->collection] = $modelObject->getDb();
        }

        file_put_contents($this->filePath , json_encode($db));
    }
}
// ---------------- Actions Layer End ----------------

// ---------------- Application Start ----------------
class Kernel
{
    const EXIT_CHOICE = 5;

    public $mainMenu = [
        1 => 'Help',
        2 => 'Add',
        3 => 'Delete',
        4 => 'Path',
        5 => 'Exit',
        6 => 'Database'
    ];

    /**
     * we will run the kernel until the user wants to exit
     */
    public function run()
    {
        do {
            $choice = readline($this->printMainMenu());

            $choice = $this->handleChoice($choice, $this->mainMenu);

            if (array_key_exists($choice, $this->mainMenu)) {
                $action = $this->mainMenu[$choice] . 'Action';
                $action = new $action;
                if ($action instanceof Action) {
                    $action->perform();
                }
            }
        } while ($choice != self::EXIT_CHOICE);
    }

    public function printMainMenu()
    {
        echo "Main Menu - Select an action:" . PHP_EOL;
        foreach ($this->mainMenu as $key => $value) {
            echo $key . '. ' . $value . PHP_EOL;
        }
    }

    public function handleChoice($choice, $choices)
    {
        if (!array_key_exists($choice, $choices)) {
            echo "Invalid input. Please enter 1 for more info." . PHP_EOL;
            return 1000;
        }

        return (int) $choice;
    }
}
// ---------------- Application End ----------------


// run the application
(new Kernel)->run();
