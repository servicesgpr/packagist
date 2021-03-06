<?php

namespace Packagist\WebBundle\Tests\Package;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\IO\NullIO;
use Composer\Package\CompletePackage;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\Vcs\GitDriver;
use Composer\Repository\Vcs\VcsDriverInterface;
use Composer\Repository\VcsRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Packagist\WebBundle\Entity\Package;
use Packagist\WebBundle\Package\Updater;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;

class UpdaterTest extends TestCase
{
    /** @var IOInterface */
    private $ioMock;
    /** @var Config */
    private $config;
    /** @var Package */
    private $package;
    /** @var Updater */
    private $updater;
    /** @var RepositoryInterface|PHPUnit_Framework_MockObject_MockObject */
    private $repositoryMock;
    /** @var VcsDriverInterface|PHPUnit_Framework_MockObject_MockObject */
    private $driverMock;

    protected function setUp()
    {
        parent::setUp();

        $this->config  = new Config();
        $this->package = new Package();

        $this->ioMock         = $this->getMockBuilder(NullIO::class)->disableOriginalConstructor()->getMock();
        $this->repositoryMock = $this->getMockBuilder(VcsRepository::class)->disableOriginalConstructor()->getMock();
        $registryMock         = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $emMock               = $this->getMockBuilder(EntityManager::class)->disableOriginalConstructor()->getMock();
        $packageMock          = $this->getMockBuilder(CompletePackage::class)->disableOriginalConstructor()->getMock();
        $this->driverMock     = $this->getMockBuilder(GitDriver::class)->disableOriginalConstructor()->getMock();

        $registryMock->expects($this->any())->method('getManager')->willReturn($emMock);
        $this->repositoryMock->expects($this->any())->method('getPackages')->willReturn([
            $packageMock
        ]);
        $this->repositoryMock->expects($this->any())->method('getDriver')->willReturn($this->driverMock);
        $packageMock->expects($this->any())->method('getRequires')->willReturn([]);
        $packageMock->expects($this->any())->method('getConflicts')->willReturn([]);
        $packageMock->expects($this->any())->method('getProvides')->willReturn([]);
        $packageMock->expects($this->any())->method('getReplaces')->willReturn([]);
        $packageMock->expects($this->any())->method('getDevRequires')->willReturn([]);

        $this->updater = new Updater($registryMock);
    }

    public function testUpdatesTheReadme()
    {
        $this->driverMock->expects($this->any())->method('getRootIdentifier')->willReturn('master');
        $this->driverMock->expects($this->any())->method('getComposerInformation')
                         ->willReturn(['readme' => 'README.md']);
        $this->driverMock->expects($this->once())->method('getFileContent')->with('README.md', 'master')
                         ->willReturn('This is the readme');

        $this->updater->update($this->ioMock, $this->config, $this->package, $this->repositoryMock);

        self::assertContains('This is the readme', $this->package->getReadme());
    }

    public function testConvertsMarkdownForReadme()
    {
        $readme = <<<EOR
# some package name

Why you should use this package:
 - it is easy to use
 - no overhead
 - minimal requirements

EOR;
        $readmeHtml = <<<EOR

<p>Why you should use this package:</p>
<ul><li>it is easy to use</li>
<li>no overhead</li>
<li>minimal requirements</li>
</ul>
EOR;

        $this->driverMock->expects($this->any())->method('getRootIdentifier')->willReturn('master');
        $this->driverMock->expects($this->any())->method('getComposerInformation')
                         ->willReturn(['readme' => 'README.md']);
        $this->driverMock->expects($this->once())->method('getFileContent')->with('README.md', 'master')
                         ->willReturn($readme);

        $this->updater->update($this->ioMock, $this->config, $this->package, $this->repositoryMock);

        self::assertSame($readmeHtml, $this->package->getReadme());
    }

    public function testSurrondsTextReadme()
    {
        $this->driverMock->expects($this->any())->method('getRootIdentifier')->willReturn('master');
        $this->driverMock->expects($this->any())->method('getComposerInformation')
                         ->willReturn(['readme' => 'README.txt']);
        $this->driverMock->expects($this->once())->method('getFileContent')->with('README.txt', 'master')
                         ->willReturn('This is the readme');

        $this->updater->update($this->ioMock, $this->config, $this->package, $this->repositoryMock);

        self::assertSame('<pre>This is the readme</pre>', $this->package->getReadme());
    }

    public function testUnderstandsDifferentFileNames()
    {
        $this->driverMock->expects($this->any())->method('getRootIdentifier')->willReturn('master');
        $this->driverMock->expects($this->any())->method('getComposerInformation')
                         ->willReturn(['readme' => 'liesmich']);
        $this->driverMock->expects($this->once())->method('getFileContent')->with('liesmich', 'master')
                         ->willReturn('This is the readme');

        $this->updater->update($this->ioMock, $this->config, $this->package, $this->repositoryMock);

        self::assertSame('<pre>This is the readme</pre>', $this->package->getReadme());
    }
}
