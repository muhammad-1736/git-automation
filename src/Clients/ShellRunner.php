<?php

namespace Mu\CherryPicker\Clients;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ShellRunner
{
    public function run(string $command): void
    {
        $process = Process::fromShellCommandline($this->goToRootFolder() . $command);
        $process->setTimeout(240);
        
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    public function runWithoutOutputAndError(string $command): array
    {
        $process = Process::fromShellCommandline($this->goToRootFolder() . $command);
        $process->setTimeout(240);
        $process->run();

        return [ 'exit_code' => $process->getExitCode(), 'error_output' => $process->getErrorOutput()];
    }

    public function runShellExec(string $command): string
    {
        return trim(shell_exec($this->goToRootFolder() . $command));
    }

    private function goToRootFolder(): string
    {
        return 'cd ' . config('cherry-picker.git.path_to_root_folder') . ' && ';
    }

}
