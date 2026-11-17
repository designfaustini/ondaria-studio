<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Services\DemoLibrary;
use App\Services\Media;
use App\Services\Stations;

final class LibraryController
{
    public const KINDS = ['normal' => 'Comum', 'time' => 'Hora certa (HHMM, ou HRSxx + MINxx)', 'temperature' => 'Temperatura (TMPxxx, TMPNxxx)', 'humidity' => 'Umidade (HUMxxx)'];

    public function index(): string
    {
        $sid = Auth::stationId();
        DemoLibrary::sync(); // lê novos áudios colocados na pasta audios/ do servidor
        $demo = DemoLibrary::id();
        $count = '(SELECT COUNT(*) FROM media m WHERE m.folder_id = f.id) AS files';
        $folders = DB::all("SELECT f.*, $count FROM folders f WHERE f.station_id = ? ORDER BY f.name", [$sid]);
        $demoFolders = $demo && $demo !== $sid ? DB::all("SELECT f.*, $count FROM folders f WHERE f.station_id = ? ORDER BY f.name", [$demo]) : [];
        $current = (int) input('pasta', 0) ?: (int) ($folders[0]['id'] ?? 0);
        $folder = null;
        foreach ([...$folders, ...$demoFolders] as $f) {
            if ((int) $f['id'] === $current) {
                $folder = $f;
            }
        }
        // Somente leitura quando a pasta é da demonstração (compartilhada com todas as rádios).
        $readonly = $folder && (int) $folder['station_id'] !== $sid;
        $media = $folder ? DB::all('SELECT * FROM media WHERE station_id = ? AND folder_id = ? ORDER BY filename', [(int) $folder['station_id'], $current]) : [];
        $st = station();
        return view('library/index', [
            'title' => 'Biblioteca',
            'folders' => $folders,
            'demoFolders' => $demoFolders,
            'readonly' => $readonly,
            'folder' => $folder,
            'media' => $media,
            'used' => Stations::usedBytes($sid),
            'quota' => (int) $st['storage_mb'] * 1024 * 1024,
            'extensions' => Media::allowedExtensions(),
            'kinds' => self::KINDS,
            'sent' => max(0, (int) input('enviados', 0)),
        ]);
    }

    public function createFolder(): void
    {
        $sid = Auth::stationId();
        $name = mb_substr(trim((string) input('name', '')), 0, 120);
        $parent = (int) input('parent_id', 0) ?: null;
        $kind = array_key_exists((string) input('kind'), self::KINDS) ? (string) input('kind') : 'normal';
        if ($name === '') {
            flash('error', 'Informe o nome da pasta.');
            back();
        }
        if ($parent && !DB::value('SELECT 1 FROM folders WHERE id = ? AND station_id = ?', [$parent, $sid])) {
            abort(404);
        }
        $id = DB::insert('folders', ['station_id' => $sid, 'parent_id' => $parent, 'name' => $name, 'kind' => $kind]);
        redirect('/biblioteca?pasta=' . $id);
    }

    public function updateFolder(int $id): void
    {
        $sid = Auth::stationId();
        $name = mb_substr(trim((string) input('name', '')), 0, 120);
        $kind = array_key_exists((string) input('kind'), self::KINDS) ? (string) input('kind') : 'normal';
        if ($name !== '') {
            DB::update('folders', ['name' => $name, 'kind' => $kind], 'id = ? AND station_id = ?', [$id, $sid]);
        }
        flash('success', 'Pasta atualizada.');
        redirect('/biblioteca?pasta=' . $id);
    }

    public function deleteFolder(int $id): void
    {
        $sid = Auth::stationId();
        $ids = Media::folderTree($sid, $id);
        if (!$ids) {
            abort(404);
        }
        if ((int) DB::value('SELECT COUNT(*) FROM folders WHERE station_id = ?', [$sid]) <= count($ids)) {
            flash('error', 'A rádio precisa ter pelo menos uma pasta.');
            back();
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (DB::all("SELECT * FROM media WHERE station_id = ? AND folder_id IN ($in)", [$sid, ...$ids]) as $m) {
            Media::delete($m);
        }
        DB::run('DELETE FROM folders WHERE id = ? AND station_id = ?', [$id, $sid]);
        \App\Core\Audit::record('folder.delete', 'folder:' . $id, count($ids) . ' pasta(s)');
        flash('success', 'Pasta, subpastas e arquivos excluídos.');
        redirect('/biblioteca');
    }

    public function updateMedia(int $id): void
    {
        $sid = Auth::stationId();
        $m = DB::one('SELECT * FROM media WHERE id = ? AND station_id = ?', [$id, $sid]);
        if (!$m) {
            abort(404);
        }
        $folder = (int) input('folder_id', $m['folder_id']);
        if (!DB::value('SELECT 1 FROM folders WHERE id = ? AND station_id = ?', [$folder, $sid])) {
            $folder = (int) $m['folder_id'];
        }
        $overlap = input('overlap_s', '');
        $filename = mb_substr(preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', (string) input('filename', $m['filename'])) ?? '', 0, 200);
        DB::update('media', [
            'title' => mb_substr((string) input('title', ''), 0, 255) ?: null,
            'artist' => mb_substr((string) input('artist', ''), 0, 255) ?: null,
            'filename' => $filename !== '' ? $filename : $m['filename'],
            'overlap_s' => $overlap === '' || $overlap === null ? null : max(0, min(60, (float) str_replace(',', '.', (string) $overlap))),
            'folder_id' => $folder,
        ], 'id = ?', [$id]);
        flash('success', 'Arquivo atualizado.');
        redirect('/biblioteca?pasta=' . $m['folder_id']);
    }

    public function deleteMedia(): void
    {
        $sid = Auth::stationId();
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $count = 0;
        foreach (array_slice($ids, 0, 1000) as $id) {
            $m = DB::one('SELECT * FROM media WHERE id = ? AND station_id = ?', [$id, $sid]);
            if ($m) {
                Media::delete($m);
                $count++;
            }
        }
        \App\Core\Audit::record('media.delete', null, $count . ' arquivo(s)');
        flash('success', $count . ' arquivo(s) excluído(s). Onde eles estavam em listas, o estúdio vai pular o item.');
        back();
    }
}
