<?php

/**
 * Copyright 2021 Markus Leupold-Löwenthal
 *
 * @license This file is part of FreeBeeGee.
 *
 * FreeBeeGee is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * FreeBeeGee is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU Affero General Public License for details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with FreeBeeGee. If not, see <https://www.gnu.org/licenses/>.
 */

namespace com\ludusleonis\freebeegee;

/**
 * FreeBeeGeeAPI - The tabletop backend.
 *
 * JSON/REST backend for FreeBeeGee.
 */
class FreeBeeGeeAPI
{
    private $version = '$VERSION$';
    private $engine = '$ENGINE$';
    private $api = null; // JSONRestAPI instance
    private $minTableGridSize = 16;
    private $maxTableGridSize = 128;

    /**
     * Constructor - setup our routes.
     */
    public function __construct()
    {
        $this->api = new JSONRestAPI();

        // best ordered by calling frequency within each method to reduce string
        // matching overhead

        // --- HEAD ---

        $this->api->register('HEAD', '/games/:gid/state/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->getStateHead($data['gid']);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        // --- GET ---

        $this->api->register('GET', '/games/:gid/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->getGame($data['gid']);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        $this->api->register('GET', '/games/:gid/state/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->getState($data['gid']);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        $this->api->register('GET', '/', function ($fbg, $data) {
            $fbg->getServerInfo();
        });

        $this->api->register('GET', '/templates/?', function ($fbg, $data) {
            $fbg->getTemplates();
        });

        $this->api->register('GET', '/games/:gid/snapshot/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->getSnapshot($data['gid']);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        $this->api->register('GET', '/games/:gid/state/save/0/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->getStateSave($data['gid'], 0);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        $this->api->register('GET', '/games/:gid/pieces/:pid/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->getPiece($data['gid'], $data['pid']);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        // --- POST ---

        $this->api->register('POST', '/games/:gid/pieces/?', function ($fbg, $data, $payload) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->createPiece($data['gid'], $payload);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        $this->api->register('POST', '/games/', function ($fbg, $data, $payload) {
            $formData = $this->api->multipartToJson();
            if ($formData) { // client sent us multipart
                $fbg->createGame($formData);
            } else { // client sent us regular json
                $fbg->createGame($payload);
            }
        });

        $this->api->register('POST', '/games/:gid/snapshot/?', function ($fbg, $data, $payload) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->postSnapshot($data['gid'], $payload);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        // --- PUT ---

        $this->api->register('PUT', '/games/:gid/pieces/:pid/?', function ($fbg, $data, $payload) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->updatePiece($data['gid'], $data['pid'], $payload);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        $this->api->register('PUT', '/games/:gid/state/?', function ($fbg, $data, $payload) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->replaceState($data['gid'], $payload);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        // --- PATCH ---

        $this->api->register('PATCH', '/games/:gid/pieces/:pid/?', function ($fbg, $data, $payload) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->updatePiece($data['gid'], $data['pid'], $payload);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });

        // --- DELETE ---

        $this->api->register('DELETE', '/games/:gid/pieces/:pid/?', function ($fbg, $data) {
            if (is_dir($this->getGameFolder($data['gid']))) {
                $fbg->deletePiece($data['gid'], $data['pid']);
            }
            $this->api->sendError(404, 'not found: ' . $data['gid']);
        });
    }

    /**
     * Run this application.
     *
     * Will route and execute a single HTTP request.
     */
    public function run(): void
    {
        $this->api->route($this);
    }

    // --- helpers -------------------------------------------------------------

    /**
     * Determine the filesystem-path where FreeBeeGee is installed in.
     *
     * This is one level up the tree from where the API script is located.
     *
     * @return string Full path to our install folder.
     */
    private function getAppFolder(): string
    {
        return $scriptDir = dirname(dirname(__FILE__)) . '/'; // app is in our parent folder
    }

    /**
     * Determine the filesystem-path where data for a particular game is stored.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @return type Full path to game data folder, including trailing slash.
     */
    private function getGameFolder(
        string $gameName
    ): string {
        return $this->api->getDataDir() . 'games/' . $gameName . '/';
    }

    /**
     * Obtain server config values.
     *
     * Done by loading server.json from disk.
     *
     * @return object Parsed server.json.
     */
    private function getServerConfig()
    {
        $config = json_decode(file_get_contents($this->api->getDataDir() . 'server.json'));
        $config->version = '$VERSION$';
        $config->engine = '$ENGINE$';
        return $config;
    }

    /**
     * Calculate the available / open game slots on this server.
     *
     * Done by counting the sub-folders in the ../games/ folder.
     *
     * @param string $json (Optional) server.json to avoid re-reading it in some cases.
     * @return int Number of currently open slots.
     */
    private function getOpenSlots(
        $json = null
    ) {
        if ($json === null) {
            $json = $this->getServerConfig();
        }

        // count games
        $dir = $this->api->getDataDir() . 'games/';
        $count = 0;
        if (is_dir($dir)) {
            $count = sizeof(scandir($this->api->getDataDir() . 'games/')) - 2; // do not count . and ..
        }

        return $json->maxGames > $count ? $json->maxGames - $count : 0;
    }

    /**
     * Remove games that were inactive too long.
     *
     * Will determine inactivity via modified-timestamp of .flock file in game
     * folder, as every sync of an client touches this.
     *
     * @param int $maxAgeSec Maximum age of inactive game in Seconds.
     */
    private function deleteOldGames($maxAgeSec)
    {
        $dir = $this->api->getDataDir() . 'games/';
        $now = time();
        if (is_dir($dir)) {
            $games = scandir($dir);
            foreach ($games as $game) {
                if ($game[0] !== '.') {
                    $modified = filemtime($dir . $game . '/.flock');
                    if ($now - $modified > $maxAgeSec) {
                        $this->api->deleteDir($dir . $game);
                    }
                }
            }
        }
    }

    /**
     * Merge two data objects.
     *
     * The second object's properties take precedence.
     *
     * @param object $original The first/source object.
     * @param object $updates An object containing new/updated properties.
     * @return object An object with $original's properties overwritten by $updates's.
     */
    private function merge(
        object $original,
        object $updates
    ): object {
        return (object) array_merge((array) $original, (array) $updates);
    }

    /**
     * Validate a game template / snapshot.
     *
     * Does a few sanity checks to see if everything is there we need. Will
     * termiante execution and send a 400 in case of invalid zips.
     *
     * @param string $zipPath Full path to the zip to check.
     */
    private function validateSnapshot(
        string $zipPath
    ) {
        $size = 0;
        $mandatory = [
            'LICENSE.md' => 'LICENSE.md',
            'state.json' => 'state.json',
            'template.json' => 'template.json',
        ];
        $optional = [
            'assets/' => 'assets/',
            'assets/tile/' => 'assets/tile/',
            'assets/token/' => 'assets/token/',
            'assets/overlay/' => 'assets/overlay/',
        ];
        $issues = [];
        $maxSize = $this->getServerConfig()->maxGameSizeMB;

        // basic tests
        if (filesize($zipPath) > $maxSize * 1024 * 1024) {
            // if the zip itself is too large, then so probably is its content
            $this->api->sendError(400, 'zip too large', 'SIZE_EXCEEDED', $issues);
        }

        // more detailed tests
        $zip = new \ZipArchive();
        if (!$zip->open($zipPath)) {
            return ['invalid zip'];
        }
        $assetCount = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);

            // filename checks
            $entryName = $entry['name'];
            if (array_key_exists($entryName, $mandatory)) {
                unset($mandatory[$entryName]);
            } elseif (array_key_exists($entryName, $optional)) {
                // just ignore
            } else {
                if (preg_match('/^assets\/(overlay|tile|token)\/[a-zA-Z0-9_.-]*.(svg|png|jpg)$/', $entryName)) {
                    $assetCount++;
                } else {
                    $issues[] = 'unexpected ' . $entryName;
                }
            }
            // filesize checks
            $entrySize = $entry['size'];
            if ($entrySize > 512 * 1024) {
                $issues[] = $entryName . ' exceeded 512kB';
            }
            $size += $entrySize;
        }
        if ($assetCount <= 0) {
            $issues[] = 'no assets found in snapshot';
        }
        foreach ($mandatory as $missing) {
            $issues[] = 'missing ' . $missing;
        }
        if ($size > $maxSize * 1024 * 1024) {
            $issues[] = 'total size exceeded server maximum of ' . $maxSize . 'MB';
            $this->api->sendError(400, 'zip too large', 'SIZE_EXCEEDED', $issues);
        }

        // report any findings so far back
        if ($issues !== []) {
            $this->api->sendError(400, 'validating snapshot failed', 'ZIP_INVALID', $issues);
        }

        // at this point the zip is formally ok, but now we look into individual files
        $this->validateTemplateJson(file_get_contents('zip://' . $zipPath . '#template.json'));
        $this->validateStateJson(file_get_contents('zip://' . $zipPath . '#state.json'));
    }

    /**
     * Validate a template.json.
     *
     * Will termiante execution and send a 400 in case of invalid JSON.
     *
     * @param string $json JSON string.
     */
    private function validateTemplateJson(
        string $json
    ) {
        $msg = 'validating template.json failed';
        $template = json_decode($json);

        // check the basics and abort on error
        if ($template === null) {
            $this->api->sendError(400, $msg, 'TEMPLATE_JSON_INVALID');
        }
        if (!property_exists($template, 'engine') || !$this->api->semverSatisfies($this->engine, $template->engine)) {
            $this->api->sendError(400, $msg, 'TEMPLATE_JSON_INVALID_ENGINE', [$template->engine, $this->engine]);
        }

        // check for more stuff
        $this->api->assertHasProperties(
            'template.json',
            $template,
            ['type', 'gridSize', 'version', 'engine', 'width', 'height', 'colors']
        );
        foreach ($template as $property => $value) {
            switch ($property) {
                case 'engine':
                    break; // was checked above
                case 'type':
                    $this->api->assertString('type', $value, 'grid-square');
                    break;
                case 'gridSize':
                    $this->api->assertInteger('gridSize', $value, 64, 64);
                    break;
                case 'version':
                    $this->api->assertSemver('version', $value);
                    break;
                case 'width':
                    $this->api->assertInteger('width', $value, $this->minTableGridSize, $this->maxTableGridSize);
                    break;
                case 'height':
                    $this->api->assertInteger('height', $value, $this->minTableGridSize, $this->maxTableGridSize);
                    break;
                case 'colors':
                    $this->api->assertObjectArray('colors', $value, 1);
                    break;
                default:
                    $this->api->sendError(400, 'invalid template.json: ' . $property . ' unkown');
            }
        }
    }

    /**
     * Validate a state.json.
     *
     * Will termiante execution and send a 400 in case of invalid JSON.
     *
     * @param string $json JSON string.
     */
    private function validateStateJson(
        string $json
    ) {
        $msg = 'validating template.json failed';
        $state = json_decode($json);
        $validated = [];

        // check the basics and abort on error
        if ($state === null) {
            $this->api->sendError(400, $msg, 'STATE_JSON_INVALID');
        }

        // check for more stuff
        $this->api->assertObjectArray('state.json', $state, 0);
        foreach ($state as $piece) {
            $validated[] = $this->validatePiece($piece, true);
        }

        return $validated;
    }

    /**
     * Install a game template/snapshot into a game.
     *
     * Will unpack the template .zip into the game folder. Terminates execution
     * on errors.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param string $zipPath Path to snapshot/template zip to install.
     * @return array The library Json for this template.
     */
    private function installSnapshot(
        string $gameName,
        string $zipPath
    ): array {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($this->getGameFolder($gameName));
            $zip->close();
            return $this->generateLibraryJson($gameName);
        } else {
            $this->api->sendError(500, 'can\'t setup template ' . $zipPath);
        }
    }

    /**
     * Update a game's state in the filesystem.
     *
     * Will update the state.json of a game with the new piece. By replacing the
     * corresponding JSON Array item with the new one via ID reference.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param object $piece The parsed & validated piece to update.
     * @param bool $create If true, this piece must not exist.
     * @return object The updated piece.
     */
    private function updatePieceState(
        string $gameName,
        object $piece,
        bool $create
    ): object {
        $folder = $this->getGameFolder($gameName);
        $lock = $this->api->waitForWriteLock($folder . '.flock');

        $oldState = json_decode(file_get_contents($folder . 'state.json'));
        $result = $piece;

        // rewrite state, starting with new item
        // only latest (first) state item per ID matters
        $now = time();
        $newState = [];
        $ids = [];
        if ($create) { // in create mode we inject the new piece
            $newState[] = $piece;
            foreach ($oldState as $stateItem) {
                if (!in_array($stateItem->id, $ids)) {
                    // for newly created items we just copy the current state of the others
                    if ($stateItem->id === $piece->id) {
                        // the ID is already in the history - abort!
                        $this->api->unlockLock($lock);
                        $this->api->sendReply(409, json_encode($piece));
                    }
                    $newState[] = $stateItem;
                    $ids[] = $stateItem->id;
                }
            }
        } else { // in update mode we lookup the piece by ID and merge the changes
            foreach ($oldState as $stateItem) {
                if (!in_array($stateItem->id, $ids)) {
                    // this is an update, and we have to patch the item if the ID matches
                    if ($stateItem->id === $piece->id) {
                        // just skip deleted piece
                        if ($piece->layer === 'delete') {
                            continue;
                        }
                        $stateItem = $this->merge($stateItem, $piece);
                        $result = $stateItem;
                    }
                    $newState[] = $stateItem;
                    $ids[] = $stateItem->id;
                }
            }
            if (!in_array($piece->id, $ids) && $piece->layer !== 'delete') {
                $this->api->unlockLock($lock);
                $this->api->sendError(404, 'not found: ' . $piece->id);
            }
        }
        $this->writeAsJsonAndDigest($folder . 'state.json', $newState);
        $this->api->unlockLock($lock);

        return $result;
    }

    /**
     * Convert an asset's filename into JSON metadata.
     *
     * Will parse files named group.myName.1x2x3.ff0000.jpg and split those
     * properties into JSON metadata.
     *
     * @param string $filename Filename to parse
     * @return object Asset object (for JSON conversion).
     */
    private function fileToAsset(
        $filename
    ) {
        $asset = new \stdClass();
        $asset->assets = [$filename];
        if (preg_match('/^(.*)\.([0-9]+)x([0-9]+)x([0-9]+)\.([a-fA-F0-9]{6})\.[a-zA-Z0-9]+$/', $filename, $matches)) {
            // name, size and color
            $asset->width = (int)$matches[2];
            $asset->height = (int)$matches[3];
            $asset->side = (int)$matches[4];
            $asset->bg = $matches[5];
            $asset->alias = $matches[1];
        } elseif (preg_match('/^(.*)\.([0-9]+)x([0-9]+)x([0-9]+)\.[a-zA-Z0-9]+$/', $filename, $matches)) {
            // name and size
            $asset->width = (int)$matches[2];
            $asset->height = (int)$matches[3];
            $asset->side = (int)$matches[4];
            $asset->bg = '808080';
            $asset->alias = $matches[1];
        } elseif (preg_match('/^(.*)\.[a-zA-Z0-9]+$/', $filename, $matches)) {
            // name only
            $asset->width = 1;
            $asset->height = 1;
            $asset->side = 1;
            $asset->bg = '808080';
            $asset->alias = $matches[1];
        }
        return $asset;
    }

    /**
     * Regenerate a library Json.
     *
     * Done by iterating over all files in the assets folder.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @return array The generated library Json data object.
     */
    private function generateLibraryJson(
        string $gameName
    ): array {
        // generate json data
        $gameFolder = $this->getGameFolder($gameName);
        $assets = [];
        foreach (['overlay', 'tile', 'token'] as $type) {
            $assets[$type] = [];
            $lastAsset = null;
            foreach (glob($gameFolder . 'assets/' . $type . '/' . '*') as $filename) {
                $asset = $this->fileToAsset(basename($filename));
                $asset->type = $type;

                // this ID only has to be unique within the game, but should be reproducable
                // therefore we use a fast hash and even only use parts of it
                $idBase = $type . '/' . $asset->alias . '.' . $asset->width . 'x' . $asset->height . 'x' . $asset->side;
                $asset->id = substr(hash('md5', $idBase), -16);
                unset($asset->side); // we don't keep the side in the json data

                if (
                    $lastAsset === null
                    || $lastAsset->alias !== $asset->alias
                    || $lastAsset->width !== $asset->width
                    || $lastAsset->height !== $asset->height
                ) {
                    // this is a new asset. write out the old.
                    if ($lastAsset !== null) {
                        array_push($assets[$type], $lastAsset);
                    }
                    $lastAsset = $asset;
                } else {
                    // this is another side of the same asset. add it to the existing one.
                    array_push($lastAsset->assets, $asset->assets[0]);
                }
            }
            if ($lastAsset !== null) { // don't forget the last one!
                array_push($assets[$type], $lastAsset);
            }
        }

        return $assets;
    }

    /**
     * Write a data object as JSON to a file and generate a digest.
     *
     * Digest will be in filename.digest. Does not do locking.
     *
     * @param $filename Path to file to write.
     * @param $object PHP object to write.
     */
    private function writeAsJsonAndDigest(
        $filename,
        $object
    ) {
        $data = json_encode($object);
        file_put_contents($filename, $data);
        file_put_contents($filename . '.digest', 'crc32:' . crc32($data));
    }

    // --- validators ----------------------------------------------------------

    /**
     * Parse incoming JSON for pieces.
     *
     * @param string $json JSON string from the client.
     * @param boolean $checkMandatory If true, this function will also ensure all
     *                mandatory fields are present.
     * @return object Validated JSON, converted to an object.
     */
    private function validatePieceJson(
        string $json,
        bool $checkMandatory
    ): object {
        $piece = $this->api->assertJson($json);
        return $this->validatePiece($piece, $checkMandatory);
    }

    /**
     * Sanity check for pieces.
     *
     * @param object $piece Full or partial piece.
     * @param boolean $checkMandatory If true, this function will also ensure all
     *                mandatory fields are present.
     * @return object New, validated object.
     */
    private function validatePiece(
        object $piece,
        bool $checkMandatory
    ): object {
        $validated = new \stdClass();
        foreach ($piece as $property => $value) {
            switch ($property) {
                case 'id':
                    $validated->id = $this->api->assertString('id', $value, '^[0-9a-f]{16}$');
                    break;
                case 'layer':
                    $validated->layer = $this->api->assertEnum('layer', $value, ['tile', 'token', 'overlay']);
                    break;
                case 'asset':
                    $validated->asset = $this->api->assertString('asset', $value, '[a-z0-9]+');
                    break;
                case 'width':
                    $validated->width = $this->api->assertInteger('width', $value, 1, 32);
                    break;
                case 'height':
                    $validated->height = $this->api->assertInteger('height', $value, 1, 32);
                    break;
                case 'x':
                    $validated->x = $this->api->assertInteger('x', $value, -100000, 100000);
                    break;
                case 'y':
                    $validated->y = $this->api->assertInteger('y', $value, -100000, 100000);
                    break;
                case 'z':
                    $validated->z = $this->api->assertInteger('z', $value, -100000, 100000);
                    break;
                case 'side':
                    $validated->side = $this->api->assertInteger('side', $value, 0, 128);
                    break;
                case 'color':
                    $validated->color = $this->api->assertInteger('color', $value, 0, 7);
                    break;
                case 'no':
                    $validated->no = $this->api->assertInteger('no', $value, 0, 26);
                    break;
                case 'r':
                    $validated->r = $this->api->assertEnum('r', $value, [0, 90, 180, 270]);
                    break;
                case 'label':
                    $validated->label = $this->api->assertString('label', $value, '^[^\n\r]{0,32}$');
                    break;
                default:
                    $this->api->sendError(400, 'invalid JSON: ' . $property . ' unkown');
            }
        }

        if ($checkMandatory) {
            $this->api->assertHasProperties(
                'piece',
                $validated,
                ['layer', 'asset', 'width', 'height', 'x', 'y', 'z', 'side', 'color'] // no
            );
        }

        return $validated;
    }

    /**
     * Parse incoming JSON for (new) games.
     *
     * @param string $json JSON string from the client.
     * @param boolean $checkMandatory If true, this function will also ensure all
     *                mandatory fields are present.
     * @return object Validated JSON, convertet to an object.
     */
    private function validateGame(
        string $json,
        bool $checkMandatory
    ): object {
        $incoming = $this->api->assertJson($json);
        $validated = new \stdClass();

        if ($checkMandatory) {
            $this->api->assertHasProperties('game', $incoming, ['name']);
        }

        foreach ($incoming as $property => $value) {
            switch ($property) {
                case 'id':
                case 'auth':
                    break; // we accept but ignore these
                case '_files':
                    $validated->_files = $value;
                    break;
                case 'name':
                    $validated->name = $this->api->assertString('name', $value, '[A-Za-z0-9]{8,48}');
                    break;
                case 'template':
                    $validated->template = $this->api->assertString('template', $value, '[A-Za-z0-9]{1,99}');
                    break;
                default:
                    $this->api->sendError(400, 'invalid JSON: ' . $property . ' unkown');
            }
        }

        return $validated;
    }

    // --- meta / server endpoints ---------------------------------------------

    /**
     * Send server info JSON to client.
     *
     * Consists of some server.json values, as well as some calculated ones. Will
     * send JSON reply and terminate execution.
     */
    private function getServerInfo()
    {
        $server = $this->getServerConfig();

        // this is a good opportunity for housekeeping
        $this->deleteOldGames(($server->ttl ?? 48) * 3600);

        // assemble json
        $info = new \stdClass();
        $info->version = $server->version;
        $info->engine = $server->engine;
        $info->ttl = $server->ttl;
        $info->snapshotUploads = $server->snapshotUploads;
        $info->openSlots = $this->getOpenSlots($server);
        if ($server->passwordCreate ?? '' !== '') {
            $info->createPassword = true;
        }
        $this->api->sendReply(200, json_encode($info));
    }

    /**
     * Sent list of available templates to client.
     *
     * Done by counting the .zip files in the templates folder. Will send JSON
     * reply and terminate execution.
     */
    private function getTemplates()
    {
        $templates = [];
        foreach (glob($this->getAppFolder() . 'templates/*zip') as $filename) {
            $zip = pathinfo($filename);
            $templates[] = $zip['filename'];
        }
        $this->api->sendReply(200, json_encode($templates));
    }

    // --- game handling endpoints ---------------------------------------------

    /**
     * Setup a new game.
     *
     * If there is a free slot available, this will create a new game folder and
     * initialize it properly. Will terminate with 201 or an error.
     *
     * @param string $payload Game JSON from client.
     */
    public function createGame(
        string $payload
    ) {
        $item = $this->api->assertJson($payload);

        // check the password (if required)
        $server = $this->getServerConfig();
        if ($server->passwordCreate ?? '' !== '') {
            if (!password_verify($item->auth ?? '', $server->passwordCreate)) {
                $this->api->sendError(401, 'valid password required');
            }
        }

        // check if we have slots left
        if ($this->getOpenSlots($server) <= 0) {
            $this->api->sendError(503, 'no more game slots available');
        }

        // sanitize item by recreating it
        $validated = $this->validateGame($payload, true);

        // we need either a template name or an uploaded snapshot
        if (
            $validated->template && $validated->_files
            || (!$validated->template && !$validated->_files )
        ) {
            $this->api->sendError(400, 'you need to either specify a template or upload a snapshot');
        }
        if ($validated->_files && !$server->snapshotUploads) {
            $this->api->sendError(400, 'snapshot upload is not enabled on this server');
        }

        // doublecheck template / snapshot
        $zipPath = ($validated->_files ?? null)
            ? ($_FILES[$validated->_files[0]]['tmp_name'] ?? 'invalid')
            : ($this->getAppFolder() . 'templates/' . $validated->template . '.zip');
        if (!is_file($zipPath)) {
            $this->api->sendError(400, 'template not available');
        }
        $this->validateSnapshot($zipPath);

        // create a new game
        $newGame = new \stdClass();
        $newGame->id = $this->generateId();
        $newGame->name = $validated->name;
        $newGame->engine = $this->engine;
        $newGame->tables = [new \stdClass()];

        $table = $newGame->tables[0];
        $table->name = 'Main';
        $table->background = new \stdClass();
        $table->background->color = '#423e3d';
        $table->background->scroller = '#2b2929';
        $table->background->image = 'img/desktop-wood.jpg';

        $folder = $this->getGameFolder($newGame->name);
        if (!is_dir($folder)) {
            if (!mkdir($folder, 0777, true)) {
                $this->api->sendError(500, 'can\'t write on server');
            }

            $lock = $this->api->waitForWriteLock($folder . '.flock');
            $table->library = $this->installSnapshot($newGame->name, $zipPath);

            // keep original state for game resets
            file_put_contents($folder . 'state-0.json', file_get_contents($folder . 'state.json'));

            // add invalid.svg to game | @codingStandardsIgnoreLine
            file_put_contents($folder . 'invalid.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 25.4 25.4" height="96" width="96"><path fill="#40bfbf" d="M0 0h25.4v25.4H0z"/><g fill="#fff" stroke="#fff" stroke-width="1.27" stroke-linecap="round" stroke-linejoin="round"><path d="M1.9 1.9l21.6 21.6M23.5 1.9L1.9 23.5" stroke-width="1.1"/></g></svg>');

            // add/overrule some template.json infos into the game.json
            $table->template = json_decode(file_get_contents($folder . 'template.json'));
            if (is_file($folder . 'LICENSE.md')) {
                $table->credits = file_get_contents($folder . 'LICENSE.md');
            } else {
                $table->credits = 'Your game template does not provide license information.';
            }
            $table->width = $table->template->width * $table->template->gridSize; // specific for 'grid-square'
            $table->height = $table->template->height * $table->template->gridSize; // specific for 'grid-square'

            $this->writeAsJsonAndDigest($folder . 'game.json', $newGame);
            $this->api->unlockLock($lock);

            $this->api->sendReply(201, json_encode($newGame), '/api/games/' . $newGame->name);
        }
        $this->api->sendReply(409, json_encode($newGame));
    }

    /**
     * Get game metadata.
     *
     * Will return the game.json from a game's folder.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     */
    public function getGame(
        string $gameName
    ) {
        $folder = $this->getGameFolder($gameName);
        if (is_dir($folder)) {
            $this->api->sendReply(200, $this->api->fileGetContentsLocked(
                $folder . 'game.json',
                $folder . '.flock'
            ));
        }
        $this->api->sendError(404, 'not found: ' . $gameName);
    }

    /**
     * Get the head of the state of a game.
     *
     * Returns a Digest HTTP header so the client can check if it's worth to
     * download the rest.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     */
    public function getStateHead(
        string $gameName
    ) {
        $folder = $this->getGameFolder($gameName);
        if (is_dir($folder)) {
            $digest = 'crc32:0';
            if (is_file($folder . 'state.json.digest')) {
                $digest = $this->api->fileGetContentsLocked(
                    $folder . 'state.json.digest',
                    $folder . '.flock'
                );
            }
            $this->api->sendReply(200, null, null, $digest);
        }
        $this->api->sendError(404, 'not found: ' . $gameName);
    }

    /**
     * Get the state of a game.
     *
     * Returns the state.json containing all pieces on the table.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     */
    public function getState(
        string $gameName
    ) {
        $folder = $this->getGameFolder($gameName);
        if (is_dir($folder)) {
            $body = $this->api->fileGetContentsLocked(
                $folder . 'state.json',
                $folder . '.flock'
            );
            $this->api->sendReply(200, $body, null, 'crc32:' . crc32($body));
        }
        $this->api->sendError(404, 'not found: ' . $gameName);
    }

    /**
     * Get a saved state of the game.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param int $slot Number between 0 and 9 of save slot, 0 = initial.
     */
    public function getStateSave(
        string $gameName,
        int $slot
    ) {
        if (!is_int($slot) || $slot < 0 || $slot > 9) {
            $this->api->sendError(404, 'save not found: ' . $gameName . ' / #' . $slot);
        }
        $folder = $this->getGameFolder($gameName);
        if (is_dir($folder)) {
            $body = $this->api->fileGetContentsLocked(
                $folder . 'state-' . $slot . '.json',
                $folder . '.flock'
            );
            $this->api->sendReply(200, $body, null, 'crc32:' . crc32($body));
        }
        $this->api->sendError(404, 'not found: ' . $gameName);
    }


    /**
     * Replace the internal state with a new one.
     *
     * Can be used to reset a table or to revert to a save.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param string $json New state JSON from client.
     */
    public function replaceState(
        string $gameName,
        string $json
    ) {
        $folder = $this->getGameFolder($gameName);
        $newState = $this->validateStateJson($json);

        $lock = $this->api->waitForWriteLock($folder . '.flock');
        $this->writeAsJsonAndDigest($folder . 'state.json', $newState);
        $this->api->unlockLock($lock);

        $this->api->sendReply(200, json_encode($newState));
    }

    /**
     * Add a new piece to a game.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param string $json Full piece JSON from client.
     */
    public function createPiece(
        string $gameName,
        string $json
    ) {
        $piece = $this->validatePieceJson($json, true);
        $piece->id = $this->generateId();
        $this->updatePieceState($gameName, $piece, true);
        $this->api->sendReply(201, json_encode($piece));
    }

    /**
     * Get an individual piece.
     *
     * Not very performant, but also not needed very often ;)
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param string $pieceId Id of piece.
     */
    public function getPiece(
        string $gameName,
        string $pieceId
    ) {
        $folder = $this->getGameFolder($gameName);
        $state = json_decode($this->api->fileGetContentsLocked(
            $folder . 'state.json',
            $folder . '.flock'
        ));

        foreach ($state as $piece) {
            if ($piece->id === $pieceId) {
                $this->api->sendReply(200, json_encode($piece));
            }
        }

        $this->api->sendError(404, 'not found: piece ' . $pieceId . ' in game ' . $gameName);
    }

    /**
     * Update a piece.
     *
     * Can overwrite the whole piece or only patch a few fields.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param string $pieceID ID of the piece to update.
     * @param string $json Full or parcial piece JSON from client.
     */
    public function updatePiece(
        string $gameName,
        string $pieceId,
        string $json
    ) {
        $patch = $this->validatePieceJson($json, false);
        $patch->id = $pieceId; // overwrite with data from URL
        $updatedPiece = $this->updatePieceState($gameName, $patch, false);
        $this->api->sendReply(200, json_encode($updatedPiece));
    }

    /**
     * Delete a piece from a game.
     *
     * Will not remove it from the library.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     * @param string $pieceID ID of the piece to delete.
     */
    public function deletePiece(
        string $gameName,
        string $pieceId
    ) {
        // create a dummy 'delete' object to represent deletion
        $piece = new \stdClass(); // sanitize item by recreating it
        $piece->layer = 'delete';
        $piece->id = $pieceId;

        $this->updatePieceState($gameName, $piece, false);
        $this->api->sendReply(204, '');
    }

    /**
     * Download a game's snapshot.
     *
     * Will zip the game folder and provide that zip.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     */
    public function getSnapshot(
        string $gameName
    ) {
        $gameFolder = realpath($this->getGameFolder($gameName));

        // get all files to zip and sort them
        $toZip = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($gameFolder),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $filename => $file) {
            if (!$file->isDir()) {
                $absolutePath = $file->getRealPath();
                $relativePath = substr($absolutePath, strlen($gameFolder) + 1);
                switch ($relativePath) { // filter those files away
                    case '.flock':
                    case 'snapshot.zip':
                    case 'invalid.svg':
                    case 'game.json':
                    case 'game.json.digest':
                    case 'state.json.digest':
                    case 'state-0.json':
                    case 'state-1.json':
                    case 'state-2.json':
                    case 'state-3.json':
                    case 'state-4.json':
                    case 'state-5.json':
                    case 'state-6.json':
                    case 'state-7.json':
                    case 'state-8.json':
                    case 'state-9.json':
                        break; // they don't go into the zip
                    default:
                        $toZip[$relativePath] = $absolutePath; // keep all others
                }
            }
        }
        ksort($toZip);

        // now zip them
        $zipName = $gameFolder . '/snapshot.zip';
        $zip = new \ZipArchive();
        $zip->open($zipName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($toZip as $relative => $absolute) {
            $zip->addFile($absolute, $relative);
        }
        $zip->close();

        // send and delete temporary file
        header('Content-disposition: attachment; filename=' . $gameName . '.' . date('Y-m-d-Hi') . '.zip');
        header('Content-type: application/zip');
        readfile($zipName);
        unlink($zipName);
    }

    /**
     * Install a game snapshot.
     *
     * Will unzip the posted payload (a zip) and try to install it as template/
     * snapshot. This will replace the current table setup.
     *
     * @param string $gameName Name of the game, e.g. 'darkEscapingQuelea'
     */
    public function postSnapshot(
        string $gameName,
        string $payload
    ) {
        $zipPath = $this->getAppFolder() . 'templates/HeroQuest.zip';
        $this->validateSnapshot($zipPath);
        $this->api->sendReply(200, "[]");
    }

    /**
     * Generate an ID.
     *
     * Central function so we can change the type of ID easily later on.
     *
     * @return {String} A random ID.
     */
    private function generateId()
    {
        return JSONRestAPI::id();
    }
}