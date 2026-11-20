<?php

/**
 * Runs EPUBCheck and DAISY Ace on a generated ePub and returns a compact result.
 * Tools are optional: when they are missing the result says so instead of claiming validity.
 */
class JatsEpubValidator
{
    private static function tools()
    {
        $configuredPath = getenv('JV_TOOLS');
        if ($configuredPath !== false && trim($configuredPath) !== '') {
            return rtrim(trim($configuredPath), '/\\');
        }
            $configuredPath = getenv('JV_TOOLS');
            if ($configuredPath !== false && trim($configuredPath) !== '') {
                return rtrim(trim($configuredPath), '/\\');
            }
            return DIRECTORY_SEPARATOR === '\\' ? 'C:/laragon/tools' : '/opt/jv-tools';
    }

    public static function validate($epubPath)
    {
        $res = ['date' => gmdate('Y-m-d\TH:i:s\Z'), 'epubcheck' => null, 'ace' => null];
        $res['epubcheck'] = self::epubcheck($epubPath);
        $res['ace'] = self::ace($epubPath);
        return $res;
    }

    private static function run($cmd, $timeout = 150)
    {
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => false]);
        if (!is_resource($proc)) {
            return [-1, ''];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $start = time();
        while (true) {
            $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $st = proc_get_status($proc);
            if (!$st['running']) {
                break;
            }
            if (time() - $start > $timeout) {
                proc_terminate($proc);
                return [-2, $out];
            }
            usleep(200000);
        }
        $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        $code = $st['exitcode'];
        foreach ($pipes as $p) {
            fclose($p);
        }
        proc_close($proc);
        return [$code, $out];
    }

    private static function epubcheck($epub)
    {
        $jar = self::tools() . '/epubcheck/epubcheck.jar';
        if (!is_file($jar)) {
            return ['status' => 'unavailable'];
        }
        list($code, $out) = self::run('java -jar ' . escapeshellarg($jar) . ' --json - ' . escapeshellarg($epub));
        if ($code === -2) {
            return ['status' => 'timeout'];
        }
        $start = (int)strpos($out, '{');
        $json = substr($out, $start, (int)strrpos($out, '}') - $start + 1);
        $data = json_decode($json, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_array($data) || !isset($data['messages'], $data['checker'])) {
            return ['status' => 'error'];
        }
        $count = ['FATAL' => 0, 'ERROR' => 0, 'WARNING' => 0];
        foreach ($data['messages'] as $it) {
            if (isset($it['severity'], $count[$it['severity']])) {
                $count[$it['severity']]++;
            }
        }
        return ['status' => ($count['FATAL'] + $count['ERROR']) ? 'fail' : 'pass', 'version' => $data['checker']['checkerVersion'] ?? '', 'errors' => $count['FATAL'] + $count['ERROR'], 'warnings' => $count['WARNING']];
    }

    private static function ace($epub)
    {
        $js = self::tools() . '/ace/node_modules/@daisy/ace/bin/ace.js';
        if (!is_file($js)) {
            return ['status' => 'unavailable'];
        }
        $outDir = sys_get_temp_dir() . '/ace-' . bin2hex(random_bytes(6));
        list($code, $log) = self::run('node ' . escapeshellarg($js) . ' -f -o ' . escapeshellarg($outDir) . ' ' . escapeshellarg($epub), 170);
        $report = $outDir . '/report.json';
        if ($code === -2 || !is_file($report)) {
            self::rm($outDir);
            return ['status' => $code === -2 ? 'timeout' : 'error'];
        }
        $r = json_decode(file_get_contents($report), true);
        self::rm($outDir);
        if (!is_array($r) || !isset($r['earl:result']['earl:outcome'])) {
            return ['status' => 'error'];
        }
        $outcome = $r['earl:result']['earl:outcome'];
        if (!in_array($outcome, ['pass', 'fail'], true)) {
            return ['status' => 'error'];
        }
        return [
            'status' => $outcome,
            'version' => $r['earl:assertedBy']['doap:release']['doap:revision'] ?? '',
            'issues' => self::countAceFailures($r['assertions'] ?? []),
        ];
    }

    private static function countAceFailures($assertions)
    {
        $count = 0;
        foreach ((array)$assertions as $assertion) {
            if (!is_array($assertion)) {
                continue;
            }
            if (isset($assertion['earl:test']) && ($assertion['earl:result']['earl:outcome'] ?? null) === 'fail') {
                $count++;
            }
            $count += self::countAceFailures($assertion['assertions'] ?? []);
        }
        return $count;
    }

    private static function rm($d)
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($d);
    }
}
