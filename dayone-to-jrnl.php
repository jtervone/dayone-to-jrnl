#!/usr/bin/php
<?php

/**
 * Converts Day One journal export (JSON) to jrln format
 *
 * @category Executable
 * @package  DayoneToJrnl
 * @author   Jarkko Tervonen <jarkko.tervonen@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     https://github.com/jtervone/dayone-to-jrnl
 */

if (!isValidConfig($argv)) {
    exit;
}

$source = $argv[1];
$target = $argv[2];

$data = readSourceData($argv[1]);

if (!$data) {
    exit;
}

$parsed = parseData($data);

foreach ($parsed as $entry) {
    writeEntry($source, $target.'journal.txt', $entry);
}

/**
 * Writes jrnl entry
 *
 * @param string $source   Source filename
 * @param string $filename Path and the filename of the target file
 * @param array  $entry    Entry data
 *
 * @return void
 */
function writeEntry($source, $filename, $entry)
{
    $data = $entry['date'].' '.$entry['text']."\n\n";

    if (is_array($entry['tags'])) {
        $tags = '';
        foreach ($entry['tags'] as $tag) {
            $tags .= '@'.str_replace(' ', '', $tag).' ';
        }
        $data .= trim($tags)."\n\n";
    }

    if (is_array($entry['photos'])) {
        $photoTrgDir = dirname($filename).'/photos';
        if (!file_exists($photoTrgDir)) {
            mkdir($photoTrgDir);
        }

        $photoSrcDir = dirname($source).'/photos';
        foreach ($entry['photos'] as $photo) {
            $photoFile = $photo['name'].'.'.$photo['type'];
            $data .= 'photos/'.$photoFile."\n";
            if (is_dir($photoSrcDir) && file_exists($photoSrcDir.'/'.$photoFile)
            ) {
                copy($photoSrcDir.'/'.$photoFile, $photoTrgDir.'/'.$photoFile);
            }
        }

        $data .= "\n";
    }

    file_put_contents($filename, $data, FILE_APPEND);
}

/**
 * Checks is configuration valid
 *
 * @param array $argv Command line arguments
 *
 * @return bool Is valid configuration
 */
function isValidConfig($argv)
{
    $source = $argv[1];
    $target = $argv[2];

    if (count($argv) !== 3) {
        echo 'Convert Day One journal export (JSON) to jrnl format'."\n\n";
        echo 'Usage: dayone-to-jrnl.php source_file target_directory'."\n\n";
        return false;
    }

    if (!file_exists($source)) {
        echo 'Source file does not exists!'."\n\n";
        return false;
    }

    if (!is_dir($target)) {
        echo 'Target directory is not directory!'."\n\n";
        return false;
    }

    if (!is_writable($target)) {
        echo 'Target directory is not writable!'."\n\n";
        return false;
    }

    return true;
}

/**
 * Parse data
 *
 * @param array $entries Day One journal entries
 *
 * @return array Parsed jrnl entries
 */
function parseData($entries)
{
    $parsed = [];

    foreach ($entries as $entry) {
        $parsed[] = parseEntry($entry);
    }

    return $parsed;
}

/**
 * Parse Day One journal entry to jrnl entry
 *
 * @param array $entry Day One entry
 *
 * @return array jrnl entry
 */
function parseEntry($entry)
{
    $parsed = [
        'date' => null,
        'text' => '',
        'tags' => [],
        'photos' => []
    ];

    $date = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $entry['creationDate']);
    $date->setTimezone(new DateTimeZone($entry['timeZone']));

    $parsed['date'] = $date->format('Y-m-d H:i');

    $text = explode("\n", $entry['text']);

    if (strpos($text[0], '![](dayone-moment://') === 0) {
        array_shift($text);
    }

    if (strlen($text[0]) === 0) {
        array_shift($text);
    }

    if (isTagLine($text[count($text) - 1])) {
        unset($text[count($text) - 1]);
    }

    $parsed['text'] = trim(implode("\n", $text));
    $parsed['tags'] = $entry['tags'];

    if (is_array($entry['photos'])) {
        foreach ($entry['photos'] as $photo) {
            $parsed['photos'][] = [
                'name' => $photo['md5'],
                'type' => $photo['type']
            ];
        }
    }

    return $parsed;
}

/**
 * Checks if line is "tag line"
 *
 * @param string $line Line
 *
 * @return bool Result
 */
function isTagLine($line)
{
    $tmp = explode(' ', $line);
    foreach ($tmp as $word) {
        if ($word{0} !== '#') {
            return false;
        }
    }

    return true;
}

/**
 * Reads Day One data file
 *
 * @param string $filename Name of the Day One journal file (JSON)
 *
 * @return array Day One journal data
 */
function readSourceData($filename)
{
    $data = json_decode(file_get_contents($filename), true);

    if (json_last_error()) {
        echo 'Errors when parsing JSON file!'."\n\n";
        return null;
    }

    if ($data['metadata']['version'] !== '1.0') {
        echo 'Unknown Day One data version!'."\n\n";
        return null;
    }

    if (isset($data['entries'])) {
        return $data['entries'];
    }

    return [];
}