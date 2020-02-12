<?php
//Reader
use Utils\FileManager;

require_once '../../bootstrap.php';

class File
{
    // tempo replicazione
    public $timeReplication = 0;
    public $timeCompilation = 0;
    public $filename = '';
    public $deadLine = 0;
    public $score = 0;
    public $dependencies = [];
    public $hasDependencies = false;
    public $alreadyCompiled = false;
    public $alreadyReplicated = false;
    public $dipendenzeSeb = [];

    public function __construct($filename, $timeCompilation, $timeReplication, $dependencies = null)
    {
        $this->filename = $filename;
        $this->timeCompilation = $timeCompilation;
        $this->timeReplication = $timeReplication;
        if ($dependencies) {
            $this->hasDependencies = true;
            $this->dependencies = $dependencies;
        }
    }

    public function getTotalWorstTime()
    {
        global $files;
        $totalTime = $this->timeCompilation;
        $max = 0;
        $worstDependency = null;

        if (!$this->hasDependencies) return $totalTime;

        for ($i = 0; $i < count($this->dependencies); $i++) {
            $dependencyTime = $files[$this->dependencies[$i]]->getTotalWorstTime();
            if ($dependencyTime > $max) {
                $max = $dependencyTime;
                $worstDependency = $this->dependencies[$i];
            }
        }
        return $totalTime + $max;
    }

    public function getTimeCompilation()
    {
        return $this->alreadyCompiled ? 0 : $this->timeCompilation;
    }

    public function calculateBestTime()
    {
        global $files;
        global $servers;
        if (!$this->hasDependencies) {
            // caso base 1
            return $this->getTimeCompilation();
        }
        if (count($this->dependencies) == 1) {
            // caso base 2
            return $files[$this->dependencies[0]]->calculateBestTime() + $this->getTimeCompilation();
        } else {
            // caso ricorsivo
            $remainingDependencies = $this->dependencies;
            $withReplication = [];
            $withoutReplication = [];
            $totalTimeWithoutReplication = 0;
            for ($i = 0; $i < count($this->dependencies); $i++) {
                $file = $files[$this->dependencies[$i]];
                $withoutReplication[$file->filename] = $file->calculateBestTime();
                $withReplication[$file->filename] = $withoutReplication[$file->filename] + $file->timeReplication;
                $totalTimeWithoutReplication += $withoutReplication[$file->filename];
            }
            $maxTimeWithReplication = 0;
            for ($i = 0; $i < count($remainingDependencies); $i++) {
                $file = $files[$remainingDependencies[$i]];
                if ($withReplication[$file->filename] > $maxTimeWithReplication)
                    $maxTimeWithReplication = $withReplication[$file->filename];
            }
            if ($totalTimeWithoutReplication < $maxTimeWithReplication) {
                return $this->getTimeCompilation() + $totalTimeWithoutReplication;
            }
            $serversUsage = [];
            $serverIndex = 0;
            do {
                if ($serverIndex > count($servers))
                    $serverIndex = 0;
                if (!isset($serversUsage[$serverIndex]))
                    $serversUsage[$serverIndex] = [];
                $fileWithMaxReplicationTime = null;
                for ($i = 0; $i < count($remainingDependencies); $i++) {
                    $file = $files[$remainingDependencies[$i]];
                    if ($file->timeReplication > $fileWithMaxReplicationTime->timeReplication)
                        $fileWithMaxReplicationTime = $file;
                }
                $remainingReplicationTime = $fileWithMaxReplicationTime->timeReplication;
                // tolgo l'elemento con tempo di replicazione più alto
                $serversUsage[$serverIndex][] = $fileWithMaxReplicationTime;
                if (($key = array_search($fileWithMaxReplicationTime->filename, $remainingDependencies)) !== false) {
                    unset($remainingDependencies[$key]);
                }
                // se ci sono elementi con un tempo totale che è minore del tempo di replicazione più alto allora li
                // faccio eseguire dallo stesso server
                for ($i = 0; $i < count($remainingDependencies); $i++) {
                    $file = $files[$remainingDependencies[$i]];
                    if ($withReplication[$file->filename] < $remainingReplicationTime) {
                        // tolgo l'elemento se ci sta
                        $serversUsage[$serverIndex][] = $file;
                        unset($remainingDependencies[$i]);
                        // ricalcolo il tempo rimanente in cui far stare una compilazione
                        $remainingReplicationTime -= $withReplication[$file->filename];
                        $i--;
                    }
                }
            } while (count($remainingDependencies) > 0);
            $totalTime = $this->getTimeCompilation();
            $maxServerUsage = 0;
            for ($i = 0; $i < count($serversUsage); $i++) {
                $serverUsage = $serversUsage[$i];
                if ($withReplication[$serverUsage[0]->filename] > $maxServerUsage)
                    $maxServerUsage = $withReplication[$serverUsage[0]->filename];
            }
            return $totalTime + $maxServerUsage;
        }
    }

    public function compileFile()
    {
        global $files;
        global $servers;
        if (!$this->hasDependencies) {
            // caso base 1
            $servers[getBestServerIndex()]->addToQueue($this);
        } elseif (count($this->dependencies) == 1) {
            // caso base 2
            $serverIndex = getBestServerIndex();
            $servers[$serverIndex]->addToQueue($files[$this->dependencies[0]]);
            $servers[$serverIndex]->addToQueue($this);
        } else {
            // caso ricorsivo
            $remainingDependencies = $this->dependencies;
            $withReplication = [];
            $withoutReplication = [];
            $totalTimeWithoutReplication = 0;
            for ($i = 0; $i < count($this->dependencies); $i++) {
                $file = $files[$this->dependencies[$i]];
                $withoutReplication[$file->filename] = $file->calculateBestTime();
                $withReplication[$file->filename] = $withoutReplication[$file->filename] + $file->timeReplication;
                $totalTimeWithoutReplication += $withoutReplication[$file->filename];
            }
            $maxTimeWithReplication = 0;
            for ($i = 0; $i < count($remainingDependencies); $i++) {
                $file = $files[$remainingDependencies[$i]];
                if ($withReplication[$file->filename] > $maxTimeWithReplication)
                    $maxTimeWithReplication = $withReplication[$file->filename];
            }
            if ($totalTimeWithoutReplication < $maxTimeWithReplication) {
                $serverIndex = getBestServerIndex();
                for ($i = 0; $i < count($remainingDependencies); $i++) {
                    $file = $files[$remainingDependencies[$i]];
                    $servers[$serverIndex]->addToQueue($file);
                }
                $servers[$serverIndex]->addToQueue($this);
                return;
            }
            do {
                $serverIndex = getBestServerIndex();
                $fileWithMaxReplicationTime = null;
                for ($i = 0; $i < count($remainingDependencies); $i++) {
                    $file = $files[$remainingDependencies[$i]];
                    if ($file->timeReplication > $fileWithMaxReplicationTime->timeReplication)
                        $fileWithMaxReplicationTime = $file;
                }
                $remainingReplicationTime = $fileWithMaxReplicationTime->timeReplication;
                // tolgo l'elemento con tempo di replicazione più alto
                $servers[$serverIndex]->addToQueue($fileWithMaxReplicationTime);
                if (($key = array_search($fileWithMaxReplicationTime->filename, $remainingDependencies)) !== false) {
                    unset($remainingDependencies[$key]);
                }
                // se ci sono elementi con un tempo totale che è minore del tempo di replicazione più alto allora li
                // faccio eseguire dallo stesso server
                for ($i = 0; $i < count($remainingDependencies); $i++) {
                    $file = $files[$remainingDependencies[$i]];
                    if ($withReplication[$file->filename] < $remainingReplicationTime) {
                        // tolgo l'elemento se ci sta
                        $servers[$serverIndex]->addToQueue($file);
                        unset($remainingDependencies[$i]);
                        // ricalcolo il tempo rimanente in cui far stare una compilazione
                        $remainingReplicationTime -= $withReplication[$file->filename];
                        $i--;
                    }
                }
            } while (count($remainingDependencies) > 0);
            $serverIndex = getBestServerIndex();
            $servers[$serverIndex]->addToQueue($this);
        }
    }

    public function getDependencies()
    {
        global $files;
        $allDependencies = $this->dependencies;
        for ($i = 0; $i < count($this->dependencies); $i++) {
            $allDependencies = array_merge($allDependencies, $files[$this->dependencies[$i]]->getDependencies());
        }
        return $allDependencies;
    }
}

class Server
{
    public $queue = [];
    public $id;
    public $currentTime = 0;

    /**
     * Server constructor.
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = (int)$id;
    }

    public function addToQueue(File $file)
    {
        global $files;
        global $compilationHistory;
        if (!$files[$file->filename]->alreadyCompiled) {
            $files[$file->filename]->alreadyCompiled = true;
            $this->currentTime += $file->timeCompilation;
            $this->queue[] = $file->filename;
            $compilationHistory[] = $file->filename . " " . $this->id;
        }
    }
}

$compilationHistory = [];

$fileName = 'a';

$servers = $files = [];

$fileManager = new FileManager($fileName);

$content = str_replace("\r", "", $fileManager->get());
$content = explode("\n", $content);

list($numCompiledFiles, $numTargetFiles, $numServers) = explode(' ', $content[0]);

for ($k = 0; $k < $numServers; $k++) {
    $servers[] = new Server($k);
}

$numCompiledFiles = (int)$numCompiledFiles;
$numTargetFiles = (int)$numTargetFiles;
$numServers = (int)$numServers;
array_shift($content);

for ($i = 0; $i < $numCompiledFiles; $i++) {
    list($fileName, $compilationTime, $replicationTime) = explode(' ', $content[$i * 2]);
    $deps = explode(' ', $content[($i * 2) + 1]);
    $dependencies = [];
    for ($j = 0; $j < $deps[0]; $j++) {
        $dependencies[] = $deps[$j + 1];
    }
    $files[$fileName] = new File($fileName, $compilationTime, $replicationTime, $dependencies);
}

$startingT = $i * 2;
for ($i = 0; $i < $numTargetFiles; $i++) {
    list($fileName, $deadline, $score) = explode(' ', $content[$i + $startingT]);
    $files[$fileName]->deadLine = (int) $deadline;
    $files[$fileName]->score = (int) $score;
}

$targetFiles = array_slice($files, -$numTargetFiles);



class FileReady
{
    public $filename;
    public $time;


    public function __construct($filename, $time)
    {
        $this->filename = $filename;
        $this->time = $time;
    }
}

class ServerManager
{
    /** @var Server $server [] */
    public $server = [];
    public $time;
    public $listaFile = [];
    public $currentTime = 0;

    public function __construct($server)
    {
        $this->server = $server;
    }

    /** @var File $file */
    public function addFile($file)
    {
        if (!self::isFilenamePresent($file->filename)) {
            // Devo scegliere su quale server metterlo
            $this->server[0]->addToQueue($file);
            $this->currentTime += $file->timeCompilation;
            $this->listaFile[] = new FileReady($file->filename, $this->currentTime);
        }
        // File già inserito per cui vai avanti
    }

    protected function isFilenamePresent($filename)
    {
        /** @var FileReady $filePresente */
        foreach ($this->listaFile as $filePresente) {
            if ($filePresente->filename === $filename)
                return true;
        }
        return false;
    }
}

//scoring
$arr = [7, ['c1', 1], ['c0', 1], ['c3',1], ['c1',1], ['c2',1], ['c4',1], ['c5',1]];

function getScore($arr)
{
    global $targetFiles, $files;
    $score = 0;
    array_shift($arr);
    foreach ($arr as $a){
        $file = $files[$a[0]];
        //$server = $a[1];
        if(in_array($file->filename, array_keys($targetFiles))){
            if($file->timeCompilation <= $file->deadLine){
                $score += $file->deadLine - $file->timeCompilation  + $file->score;
            }
        }
        /*$file = $a[0]['name'];
        $server = $a[1];
        if(in_array($file, array_keys($targetFiles))){
            if($a[0]['compiled'] <= $a[0]['deadline']){
                $score += $a[0]['deadline'] - $a[0]['compiled'] + $a[0]['goal'];
            }
        }*/
    }
    echo 'SCORE: '.$score.PHP_EOL;
}
getScore($arr);
