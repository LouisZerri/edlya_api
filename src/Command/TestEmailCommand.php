<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:test-email',
    description: 'Envoie un email de test',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $fromName
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse email destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        $io->info("Envoi d'un email de test à $to...");

        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject('Test Edlya - Configuration email')
                ->html('<h1>Configuration réussie !</h1><p>Si vous recevez cet email, la configuration SMTP fonctionne correctement.</p>');

            $this->mailer->send($email);

            $io->success("Email envoyé avec succès à $to");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Erreur lors de l'envoi : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
