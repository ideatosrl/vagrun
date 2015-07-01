<?php

namespace Ideato\Vagrun\Test;

use Ideato\Vagrun\ConfigCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

class ConfigCommandTest extends \PHPUnit_Framework_TestCase
{
    protected $currentDir;

    protected function setUp()
    {
        $this->currentDir = sys_get_temp_dir() . '/vagrun.' . uniqid(time()) . '/';
        shell_exec('mkdir ' . $this->currentDir);
        shell_exec(sprintf('cp %s %s/Vagrantfile', __DIR__ . '/../../fixtures/Vagrantfile.template', $this->currentDir));
        shell_exec('cd ' . $this->currentDir . '&& mkdir vagrant && touch vagrant/vagrantconfig.yml');

        $config = <<<EOD
ram: 2048
cpus: 2
ipaddress: 10.10.10.10
name: vagrant-box-name
EOD;
        file_put_contents($this->currentDir . 'vagrant/vagrantconfig.yml', $config);
    }

    public function testExecute()
    {
        $application = new Application();
        $application->add(new ConfigCommand());

        $command = $application->find('config');

        $commandTester = new CommandTester($command);

        $helper = $command->getHelper('question');
        $helper->setInputStream($this->getInputStream(
            "1024\n".
            "2\n".
            "10.10.10.111\n".
            "test-box\n".
            "/var/www/vagrun\n"
        ));

        $commandTester->execute(array(
            'command' => $command->getName(),
            '--path' => $this->currentDir
        ));

        $output = $commandTester->getDisplay();
        $this->assertContains('ram: 1024', $output);
        $this->assertContains('cpus: 2', $output);
        $this->assertContains('ipaddress: 10.10.10.111', $output);
        $this->assertContains('name: test-box', $output);
        $this->assertContains('Synced folder: /var/www/vagrun', $output);

        $yaml = Yaml::parse(file_get_contents($this->currentDir . 'vagrant/vagrantconfig.yml'));
        $expected = array(
            "ram" => 1024,
            "cpus" => 2,
            "ipaddress" => '10.10.10.111',
            "name" => 'test-box'
        );
        $this->assertEquals($expected, $yaml);

        $vagrantFile = file_get_contents($this->currentDir . 'Vagrantfile');
        $this->assertEquals(2, substr_count($vagrantFile, 'vagrant/vagrantconfig.yml'));
        $this->assertEquals(3, substr_count($vagrantFile, '/var/www/vagrun'));
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
    }

    protected function tearDown()
    {
        shell_exec('rm -rf ' . $this->currentDir);
    }
}