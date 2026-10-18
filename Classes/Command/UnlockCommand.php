<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace GeorgRinger\MfaUnlock\Command;

use GeorgRinger\MfaUnlock\Service\MfaUnlockService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('mfa:unlock', 'List backend users with a locked MFA provider and unlock them.')]
final class UnlockCommand extends Command
{
    public function __construct(private readonly MfaUnlockService $mfaUnlockService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::OPTIONAL, 'uid or username of the backend user to unlock')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Unlock all backend users')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List the backend users with a locked MFA provider');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user = (string)$input->getArgument('user');
        $all = (bool)$input->getOption('all');
        $list = (bool)$input->getOption('list');

        if ((int)($user !== '') + (int)$all + (int)$list !== 1) {
            $io->error('Provide exactly one of: a uid or username, --all or --list.');
            return Command::INVALID;
        }

        if ($list) {
            return $this->listLockedUsers($io);
        }
        if ($all) {
            return $this->unlockAll($io);
        }
        return $this->unlockUser($io, $user);
    }

    private function listLockedUsers(SymfonyStyle $io): int
    {
        $lockedUsers = $this->mfaUnlockService->findLockedUsers();
        if ($lockedUsers === []) {
            $io->success('No backend user has a locked MFA provider.');
            return Command::SUCCESS;
        }
        $io->table(
            ['uid', 'username', 'name', 'locked providers'],
            array_map(
                static fn($lockedUser): array => [
                    $lockedUser->uid,
                    $lockedUser->username . ($lockedUser->disabled ? ' (disabled)' : ''),
                    $lockedUser->realName,
                    implode(', ', array_keys($lockedUser->providers)),
                ],
                $lockedUsers
            )
        );
        return Command::SUCCESS;
    }

    private function unlockAll(SymfonyStyle $io): int
    {
        $lockedUsers = $this->mfaUnlockService->findLockedUsers();
        if ($lockedUsers === []) {
            $io->success('No backend user has a locked MFA provider.');
            return Command::SUCCESS;
        }
        $failed = false;
        foreach ($lockedUsers as $lockedUser) {
            $row = $this->mfaUnlockService->findUser((string)$lockedUser->uid);
            $unlocked = $row === null ? [] : $this->mfaUnlockService->unlock($row);
            if ($unlocked === []) {
                $io->warning(sprintf('Could not unlock "%s" [%d].', $lockedUser->username, $lockedUser->uid));
                $failed = true;
                continue;
            }
            $io->writeln(sprintf('Unlocked "%s" [%d]: %s', $lockedUser->username, $lockedUser->uid, implode(', ', $unlocked)));
        }
        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function unlockUser(SymfonyStyle $io, string $user): int
    {
        $row = $this->mfaUnlockService->findUser($user);
        if ($row === null) {
            $io->error(sprintf('No backend user found for "%s".', $user));
            return Command::FAILURE;
        }
        $unlocked = $this->mfaUnlockService->unlock($row);
        if ($unlocked === []) {
            $io->note(sprintf('"%s" [%d] has no locked MFA provider.', $row['username'], $row['uid']));
            return Command::SUCCESS;
        }
        $io->success(sprintf('Unlocked "%s" [%d]: %s', $row['username'], $row['uid'], implode(', ', $unlocked)));
        return Command::SUCCESS;
    }
}
