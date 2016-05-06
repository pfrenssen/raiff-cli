<?php

namespace RaiffCli\Command;

use IBAN\Validation\IBANValidator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Command that adds a recipient to the list.
 */
class RecipientAdd extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('recipient:add')
            ->setDescription('Add a recipient.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the recipient')
            ->addArgument('iban', InputArgument::REQUIRED, 'The IBAN of the recipient.')
            ->addArgument('alias', InputArgument::REQUIRED, 'An alias to identify this recipient');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        // Ask for the recipient name.
        if (empty($name)) {
            $helper = $this->getHelper('question');
            $question = new Question('Recipient name: ');
            $question->setValidator([$this, 'requiredValidator']);
            $name = $helper->ask($input, $output, $question);
            $input->setArgument('name', $name);
        }

        // Ask for the IBAN.
        if (empty($iban)) {
            $helper = $this->getHelper('question');
            $question = new Question('IBAN: ');
            $question->setValidator([$this, 'validateIban']);
            $input->setArgument('iban', $helper->ask($input, $output, $question));
        }
        
        // Ask for the alias.
        if (empty($alias)) {
            $helper = $this->getHelper('question');
            $question = new Question('Alias: ', $name);
            $question->setValidator([$this, 'validateAlias']);
            $input->setArgument('alias', $helper->ask($input, $output, $question));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');
        $iban = $input->getArgument('iban');
        $alias = $input->getArgument('alias');

        /** @var \RaiffCli\Config\Config $config */
        $config = $this->getConfigManager()->get('recipients');
        $recipients = $config->get('recipients', []);
        $recipients[] = ['alias' => $alias, 'name' => $name, 'iban' => $iban];

        usort($recipients, function ($a, $b) {
            return strcmp($a['alias'], $b['alias']);
        });

        $config->set('recipients', $recipients);
        $config->save();

        $output->writeln("Added recipient '$name' with IBAN '$iban'.");
    }

    /**
     * Validates the alias.
     *
     * @param string $input
     *   The alias to validate.
     *
     * @return string
     *   The validated alias.
     *
     * @throws \InvalidArgumentException
     *   Thrown when the alias is not empty or already exists.
     */
    public function validateAlias($input)
    {
        $input = $this->requiredValidator($input);
        $config = $this->getConfigManager()->get('recipients');
        $recipients = $config->get('recipients', []);
        foreach ($recipients as $recipient) {
            if ($recipient['alias'] === $input) {
                throw new \InvalidArgumentException('A recipient with this alias already exists.');
            }
        }

        return $input;
    }

    /**
     * Validates an IBAN.
     *
     * @param string $input
     *   The IBAN to validate.
     *
     * @return string
     *   The validated IBAN.
     *
     * @throws \InvalidArgumentException
     *   Thrown when the IBAN is not valid.
     */
    public function validateIban($input)
    {
        $validator = new IBANValidator();
        if ($validator->validate($input)) {
            return $input;
        }

        throw new \InvalidArgumentException('Invalid IBAN.');
    }

}
