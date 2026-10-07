<?php

namespace App\Services\Vpn;

use Throwable;

class ChrSsh implements RunsChrCommands
{
    public function run(string $command): array
    {
        if (! VpnChrSettings::configured()) {
            return [
                'ok' => false,
                'output' => '',
                'message' => 'Kredensial SSH CHR belum diisi.',
            ];
        }

        $directory = sys_get_temp_dir().'/vpn-ssh-'.bin2hex(random_bytes(6));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            return [
                'ok' => false,
                'output' => '',
                'message' => 'Folder sementara SSH tidak bisa dibuat.',
            ];
        }

        $askpass = $directory.'/askpass';
        $knownHosts = $directory.'/known_hosts';
        file_put_contents($askpass, "#!/bin/sh\nprintf '%s\\n' ".escapeshellarg(VpnChrSettings::password())."\n");
        chmod($askpass, 0700);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        try {
            $process = proc_open(
                [
                    'ssh',
                    '-T',
                    '-p', (string) VpnChrSettings::port(),
                    '-o', 'StrictHostKeyChecking=accept-new',
                    '-o', 'UserKnownHostsFile='.$knownHosts,
                    '-o', 'PreferredAuthentications=password',
                    '-o', 'PubkeyAuthentication=no',
                    '-o', 'NumberOfPasswordPrompts=1',
                    '-o', 'ConnectTimeout=12',
                    VpnChrSettings::username().'@'.VpnChrSettings::host(),
                    $command,
                ],
                $descriptors,
                $pipes,
                $directory,
                [
                    'SSH_ASKPASS' => $askpass,
                    'SSH_ASKPASS_REQUIRE' => 'force',
                    'DISPLAY' => 'none',
                    'HOME' => $directory,
                ],
            );

            if (! is_resource($process)) {
                return ['ok' => false, 'output' => '', 'message' => 'SSH CHR tidak bisa dijalankan.'];
            }

            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]) ?: '';
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $output = trim($stdout."\n".$stderr);

            if ($exit !== 0 || preg_match('/failure:|bad command name|expected end of command|syntax error/i', $output) === 1) {
                return [
                    'ok' => false,
                    'output' => $output,
                    'message' => $output !== '' ? $output : 'Perintah CHR gagal.',
                ];
            }

            return ['ok' => true, 'output' => $output, 'message' => 'Perintah CHR berhasil.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'output' => '', 'message' => $exception->getMessage()];
        } finally {
            @unlink($askpass);
            @unlink($knownHosts);
            @rmdir($directory);
        }
    }
}
