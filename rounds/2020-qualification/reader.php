<?php

use Utils\Collection;
use Utils\FileManager;

require_once '../../bootstrap.php';

class Book
{
    public $id;
    public $award;
    public $awardWeighted;

    /** @var Collection $inLibraries */
    public $inLibraries;
    public $inLibrariesCount;

    public function __construct($id, $award)
    {
        $this->id = $id;
        $this->award = $award;
        $this->inLibraries = collect();
    }
}

class Library
{
    public $id;
    public $signUpDuration;
    public $shipsPerDay;

    /** @var Collection $books */
    /** @var Collection $booksChunked */
    /** @var Collection $booksChunkedScore */
    public $books;
    public $booksChunked;
    public $booksChunkedScore;

    public $done = false;
    public $booksNumber = 0;

    public $librariesConnected = [];
    public $librariesConnectedCount = 0;

    public $percentileChunkScore = 0;

    public $uniqueness = 0;
    public $booksCut = null;
    public $booksCutScore = 0;
    public $booksCutCount = 0;

    public function __construct($id, $fileRow1, $fileRow2)
    {
        global $books;
        $this->id = $id;
        $this->books = [];
        list($booksCount, $this->signUpDuration, $this->shipsPerDay) = explode(' ', $fileRow1);
        foreach (explode(' ', $fileRow2) as $bookId) {
            /** @var Book $book */
            $book = $books[$bookId];
            $this->books[$bookId] = $book;
            $book->inLibraries->put($id, $this);
        }
        $book->inLibrariesCount = $book->inLibraries->count();
        $this->books = collect($this->books)->keyBy('id');
        $this->booksNumber = $booksCount;
    }
}

/**
 * Runtime
 */

// Reading the inputs
$fileManager = new FileManager($fileName);
$content = explode("\n", $fileManager->get());

list($countBooks, $countLibraries, $countDays) = explode(' ', $content[0]);

$books = [];
foreach (explode(' ', $content[1]) as $id => $bookAward) {
    $books[$id] = new Book($id, $bookAward);
}
$books = collect($books)->keyBy('id');

$libraries = [];
$librariesRows = array_slice($content, 2, count($content));

$id = 0;
for ($line = 0; $line < count($librariesRows); $line += 2) {
    $libraries[$id] = new Library($id, $librariesRows[$line], $librariesRows[$line + 1]);
    $id++;
}
$libraries = collect($libraries)->keyBy('id');
