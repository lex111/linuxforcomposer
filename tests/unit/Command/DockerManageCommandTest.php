<?php

/**
 * Linux for PHP/Linux for Composer
 *
 * Copyright 2010 - 2019 Foreach Code Factory <lfphp@asclinux.net>
 * Version 1.0.2
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package    Linux for PHP/Linux for Composer
 * @copyright  Copyright 2010 - 2019 Foreach Code Factory <lfphp@asclinux.net>
 * @link       http://linuxforphp.net/
 * @license    Apache License, Version 2.0, see above
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 * @since 0.9.8
 */

namespace LinuxforcomposerTest\Command;

use Linuxforcomposer\Command\DockerManageCommand;
use LinuxforcomposerTest\Mock\InputMock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DockerManageCommandTest extends KernelTestCase
{
    protected $dockerLfcProcessMock;

    protected $progressBarMock;

    public static function setUpBeforeClass()
    {
        if (!defined('PHARFILENAME')) {
            define(
                'PHARFILENAME',
                dirname(__DIR__)
                . DIRECTORY_SEPARATOR
                . 'app'
                . DIRECTORY_SEPARATOR
                . 'app.php'
            );
        }

        if (!defined('JSONFILEDIST')) {
            define(
                'JSONFILEDIST',
                dirname(__DIR__)
                . DIRECTORY_SEPARATOR
                . 'app'
                . DIRECTORY_SEPARATOR
                . 'linuxforcomposer.test.dist.json'
            );
        }

        if (!defined('JSONFILE')) {
            define(
                'JSONFILE',
                dirname(__DIR__)
                . DIRECTORY_SEPARATOR
                . 'app'
                . DIRECTORY_SEPARATOR
                . 'linuxforcomposer.test.json'
            );
        }

        if (!defined('VENDORFOLDERPID')) {
            define(
                'VENDORFOLDERPID',
                dirname(__DIR__)
                . DIRECTORY_SEPARATOR
                . 'app'
            );
        }
    }

    public function tearDown()
    {
        \Mockery::close();

        parent::tearDown();
    }

    public function createMocksForUnixEnv()
    {
        $this->dockerLfcProcessMock = \Mockery::mock('overload:Linuxforcomposer\Helper\LinuxForComposerProcess');
        $this->dockerLfcProcessMock
            ->shouldReceive('isTtySupported')
            ->withAnyArgs();
        $this->dockerLfcProcessMock
            ->shouldReceive('setTty')
            ->withAnyArgs();
        $this->dockerLfcProcessMock
            ->shouldReceive('setTimeout')
            ->once()
            ->with(null);
        $this->dockerLfcProcessMock
            ->shouldReceive('prepareProcess')
            ->once();
        $this->dockerLfcProcessMock
            ->shouldReceive('start')
            ->once();
        $this->dockerLfcProcessMock
            ->shouldReceive('wait')
            ->withAnyArgs();

        $this->progressBarMock = \Mockery::mock('overload:Symfony\Component\Console\Helper\ProgressBar');
        $this->progressBarMock
            ->shouldReceive('setFormatDefinition')
            ->once()
            ->with('normal_nomax_nocurrent', ' Working on it... [%bar%]');
        $this->progressBarMock
            ->shouldReceive('setFormat')
            ->once()
            ->with('normal_nomax_nocurrent');
        $this->progressBarMock
            ->shouldReceive('start')
            ->once();
        $this->progressBarMock
            ->shouldReceive('finish')
            ->once();
    }

    public function testCheckImageWithImageAvailabilitySuccess()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('isSuccessful')
            ->andReturn(true);
        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('We downloaded the image!');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->once()
            ->andReturn('One download failed');
        $this->dockerLfcProcessMock
            ->shouldReceive('getExitCode')
            ->once()
            ->andReturn(0);

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandReflection = new \ReflectionClass($command);

        $methodsList = $commandReflection->getMethods();

        for ($i = 0; $i < count($methodsList); $i++) {
            $key = $methodsList[$i]->name;
            $commandMethods[$key] = $methodsList[$i];
            $commandMethods[$key]->setAccessible(true);
        }

        $output = $commandMethods['checkImage']->invokeArgs(
            $command,
            array('7.2.5-nts', 'nts', 'lfphp')
        );

        $this->assertSame(
            'asclinux/linuxforphp-8.1:7.2.5-nts lfphp',
            $output
        );

        $this->assertSame(
            PHP_EOL
            . 'Checking for image availability and downloading if necessary.'
            . PHP_EOL
            . PHP_EOL
            . 'This may take a few minutes...'
            . PHP_EOL
            . PHP_EOL
            . 'We downloaded the image!'
            . PHP_EOL
            . 'One download failed'
            . PHP_EOL
            . 'Done!'
            . PHP_EOL
            . PHP_EOL,
            $this->getActualOutput()
        );

        $output2 = $commandMethods['checkImage']->invokeArgs(
            $command,
            array('7.1.16-zts', 'zts', '/bin/bash')
        );

        $this->assertSame(
            'asclinux/linuxforphp-8.1:7.1.16-zts /bin/bash',
            $output2
        );

        $output3 = $commandMethods['checkImage']->invokeArgs(
            $command,
            array('7.0.29-nts', 'nts', '/bin/bash')
        );

        $this->assertSame(
            'asclinux/linuxforphp-8.1:7.0.29-nts /bin/bash',
            $output3
        );
    }

    public function testCheckImageWithImageAvailabilityFailure()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('isSuccessful')
            ->andReturn(true);
        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('We downloaded the image!');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->once()
            ->andReturn('One download failed');
        $this->dockerLfcProcessMock
            ->shouldReceive('getExitCode')
            ->once()
            ->andReturn(1);

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandReflection = new \ReflectionClass($command);

        $methodsList = $commandReflection->getMethods();

        for ($i = 0; $i < count($methodsList); $i++) {
            $key = $methodsList[$i]->name;
            $commandMethods[$key] = $methodsList[$i];
            $commandMethods[$key]->setAccessible(true);
        }

        $output = $commandMethods['checkImage']->invokeArgs(
            $command,
            array('7.3.5-nts', 'nts', 'lfphp')
        );

        $this->assertSame(
            'asclinux/linuxforphp-8.1:src '
            . '/bin/bash -c \'lfphp-compile 7.3.5 nts ; lfphp\'',
            $output
        );

        $this->assertSame(
            PHP_EOL
            . 'Checking for image availability and downloading if necessary.'
            . PHP_EOL
            . PHP_EOL
            . 'This may take a few minutes...'
            . PHP_EOL
            . PHP_EOL
            . 'We downloaded the image!'
            . PHP_EOL
            . 'One download failed'
            . PHP_EOL
            . 'Done!'
            . PHP_EOL
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testFormatInput()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('isSuccessful')
            ->andReturn(true);
        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('We downloaded the image!');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->once()
            ->andReturn('One download failed');
        $this->dockerLfcProcessMock
            ->shouldReceive('getExitCode')
            ->once()
            ->andReturn(0);

        $dockerManageCommandFake = new DockerManageCommand();
        $commandReflection = new \ReflectionClass($dockerManageCommandFake);

        $methodsList = $commandReflection->getMethods();

        for ($i = 0; $i < count($methodsList); $i++) {
            $key = $methodsList[$i]->name;
            $commandMethods[$key] = $methodsList[$i];
            $commandMethods[$key]->setAccessible(true);
        }

        $arguments = array(
            'command' => 'docker:run',
            'interactive' => true,
            'tty'  => true,
            'detached'  => true,
            'phpversion' => '7.2.5',
            'threadsafe' => 'nts',
            'port' => '8181:80',
            'volume' => '${PWD}/:/srv/www',
            'script' => 'lfphp',
            'execute' => 'run',
        );

        $arrayInputFake = new InputMock();
        $arrayInputFake->setArguments($arguments);

        $output = $commandMethods['formatInput']->invokeArgs(
            $dockerManageCommandFake,
            array($arrayInputFake)
        );

        $this->assertSame(
            'docker run --restart=always -i -t -d -p 8181:80 '
            . '-v ${PWD}/:/srv/www asclinux/linuxforphp-8.1:7.2.5-nts lfphp',
            $output
        );

        $this->assertSame(
            PHP_EOL
            . 'Checking for image availability and downloading if necessary.'
            . PHP_EOL
            . PHP_EOL
            . 'This may take a few minutes...'
            . PHP_EOL
            . PHP_EOL
            . 'We downloaded the image!'
            . PHP_EOL
            . 'One download failed'
            . PHP_EOL
            . 'Done!'
            . PHP_EOL
            . PHP_EOL,
            $this->getActualOutput()
        );

        $dockerManageCommandFake = new DockerManageCommand();
        $commandReflection = new \ReflectionClass($dockerManageCommandFake);

        $methodsList = $commandReflection->getMethods();

        for ($i = 0; $i < count($methodsList); $i++) {
            $key = $methodsList[$i]->name;
            $commandMethods[$key] = $methodsList[$i];
            $commandMethods[$key]->setAccessible(true);
        }

        $arguments = array(
            'command' => 'docker:run',
            'interactive' => true,
            'tty'  => true,
            'detached'  => true,
            'phpversion' => '7.2.5',
            'threadsafe' => 'nts',
            'port' => array(
                '8181:80',
                '3306:3306',
            ),
            'volume' => array(
                '${PWD}/:/srv/www',
                '${PWD}/:/srv/test',
            ),
            'script' => 'lfphp',
            'execute' => 'run',
        );

        $arrayInputFake = new InputMock();
        $arrayInputFake->setArguments($arguments);

        $output = $commandMethods['formatInput']->invokeArgs(
            $dockerManageCommandFake,
            array($arrayInputFake)
        );

        $this->assertSame(
            'docker run --restart=always -i -t -d -p 8181:80 -p 3306:3306 '
            . '-v ${PWD}/:/srv/www -v ${PWD}/:/srv/test asclinux/linuxforphp-8.1:7.2.5-nts lfphp',
            $output
        );

        $dockerManageCommandFake = new DockerManageCommand();
        $commandReflection = new \ReflectionClass($dockerManageCommandFake);

        $methodsList = $commandReflection->getMethods();

        for ($i = 0; $i < count($methodsList); $i++) {
            $key = $methodsList[$i]->name;
            $commandMethods[$key] = $methodsList[$i];
            $commandMethods[$key]->setAccessible(true);
        }

        $arguments = array(
            'command' => 'docker:run',
            'interactive' => true,
            'tty'  => true,
            'detached'  => true,
            'phpversion' => 'custom-7.2.5',
            'threadsafe' => 'nts',
            'port' => array(
                '8181:80',
                '3306:3306',
            ),
            'volume' => array(
                '${PWD}/:/srv/www',
                '${PWD}/:/srv/test',
            ),
            'script' => 'lfphp',
            'execute' => 'run',
        );

        $arrayInputFake = new InputMock();
        $arrayInputFake->setArguments($arguments);

        $output = $commandMethods['formatInput']->invokeArgs(
            $dockerManageCommandFake,
            array($arrayInputFake)
        );

        $this->assertSame(
            'docker run --restart=always -i -t -d -p 8181:80 -p 3306:3306 '
            . '-v ${PWD}/:/srv/www -v ${PWD}/:/srv/test asclinux/linuxforphp-8.1:custom-7.2.5-nts lfphp',
            $output
        );
    }

    public function testExecuteWithRunCommand()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('isRunning')
            ->andReturn(false);
        $this->dockerLfcProcessMock
            ->shouldReceive('isSuccessful')
            ->andReturn(true);
        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->andReturn('');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->andReturn('');
        $this->dockerLfcProcessMock
            ->shouldReceive('getExitCode')
            ->andReturn(0);

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'run',
        ));

        $this->assertSame(
            PHP_EOL
            . 'Checking for image availability and downloading if necessary.'
            . PHP_EOL
            . PHP_EOL
            . 'This may take a few minutes...'
            . PHP_EOL
            . PHP_EOL
            . 'Done!'
            . PHP_EOL
            . PHP_EOL
            . 'Starting container...'
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithRunCommandWithStdoutAndStderrFromCommands()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('isRunning')
            ->andReturn(false);
        $this->dockerLfcProcessMock
            ->shouldReceive('isSuccessful')
            ->andReturn(true);
        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->andReturn('Fake containers started...');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->andReturn('We have received a few errors...');
        $this->dockerLfcProcessMock
            ->shouldReceive('getExitCode')
            ->andReturn(0);

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'run',
        ));

        $this->assertSame(
            PHP_EOL
            . 'Checking for image availability and downloading if necessary.'
            . PHP_EOL
            . PHP_EOL
            . 'This may take a few minutes...'
            . PHP_EOL
            . PHP_EOL
            . 'Fake containers started...'
            . PHP_EOL
            . 'We have received a few errors...'
            . PHP_EOL
            . 'Done!'
            . PHP_EOL
            . PHP_EOL
            . 'Starting container...'
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithStopCommand()
    {
        file_put_contents(
            VENDORFOLDERPID
            . DIRECTORY_SEPARATOR
            . 'composer'
            . DIRECTORY_SEPARATOR
            . 'linuxforcomposer.pid',
            'a1a1' . PHP_EOL
        );

        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->andReturn('');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->andReturn('');

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(array('n'));
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'stop',
        ));

        $this->assertSame(
            PHP_EOL . 'Stopping container...' . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithStopCommandWithStdoutAndStderrFromCommands()
    {
        file_put_contents(
            VENDORFOLDERPID
            . DIRECTORY_SEPARATOR
            . 'composer'
            . DIRECTORY_SEPARATOR
            . 'linuxforcomposer.pid',
            'a1a1' . PHP_EOL
        );

        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('Fake containers stopped and removed!');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->andReturn('We have received a few errors...');

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(array('n'));
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'stop',
        ));

        $this->assertSame(
            'Fake containers stopped and removed!'
            . PHP_EOL
            . 'Stopping container...'
            . PHP_EOL
            . 'Fake containers stopped and removed!'
            . PHP_EOL
            . 'We have received a few errors...'
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithStopCommandWithCommitCommandWithoutOutputToJsonFile()
    {
        file_put_contents(
            VENDORFOLDERPID
            . DIRECTORY_SEPARATOR
            . 'composer'
            . DIRECTORY_SEPARATOR
            . 'linuxforcomposer.pid',
            'a1a1' . PHP_EOL
        );

        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('Container a1a1');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->andReturn('');

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(array('y'));
        $commandTester->setInputs(array('test-7.2.5'));
        $commandTester->setInputs(array('n'));
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'stop',
        ));

        $this->assertSame(
            'Container a1a1'
            . PHP_EOL
            . 'Stopping container...'
            . PHP_EOL
            . 'Container a1a1'
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    /**
     * @TODO
     * ** TEST DEACTIVATED **
     */
    public function executeWithStopCommandWithCommitCommandWithOutputToJsonFile()
    {
        file_put_contents(
            VENDORFOLDERPID
            . DIRECTORY_SEPARATOR
            . 'composer'
            . DIRECTORY_SEPARATOR
            . 'linuxforcomposer.pid',
            'a1a1' . PHP_EOL
        );

        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('Container a1a1');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->andReturn('');

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(array('y'));
        $commandTester->setInputs(array('test-7.2.5'));

        // ** TEST DEACTIVATED **
        // The next line is causing a runtime exception within the Symfony console!
        $commandTester->setInputs(array('y'));
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'stop',
        ));

        $this->assertSame(
            'Container a1a1'
            . PHP_EOL
            . 'Stopping container...'
            . PHP_EOL
            . 'Container a1a1'
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithStopCommandWithEmptyPidFile()
    {
        file_put_contents(
            VENDORFOLDERPID
            . DIRECTORY_SEPARATOR
            . 'composer'
            . DIRECTORY_SEPARATOR
            . 'linuxforcomposer.pid',
            '' . PHP_EOL
        );

        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->once()
            ->andReturn('');

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'stop',
        ));

        $this->assertSame(
            PHP_EOL
            . 'PID file was empty!'
            . PHP_EOL
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithStopCommandWithoutPidFile()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $this->createMocksForUnixEnv();

        $this->dockerLfcProcessMock
            ->shouldReceive('getOutput')
            ->once()
            ->andReturn('');
        $this->dockerLfcProcessMock
            ->shouldReceive('getErrorOutput')
            ->once()
            ->andReturn('');

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'stop',
        ));

        $this->assertSame(
            PHP_EOL
            . 'Could not find the PID file!'
            . PHP_EOL
            . 'Please make sure the file exists or stop the containers manually.'
            . PHP_EOL
            . PHP_EOL,
            $this->getActualOutput()
        );
    }

    public function testExecuteWithWrongCommand()
    {
        // Redirect output to command output
        $this->setOutputCallback(function () {
        });

        $kernel = self::bootKernel();

        $application = new Application($kernel);
        $application->add(new DockerManageCommand());

        $command = $application->find('docker:manage');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'execute'  => 'bad',
        ));

        $this->assertSame(
            PHP_EOL
            . 'Wrong command given!'
            . PHP_EOL
            . PHP_EOL,
            $this->getActualOutput()
        );
    }
}
