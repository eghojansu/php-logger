<?php

use Ekok\Logger\Log;

class LogTest extends \Codeception\Test\Unit
{
    public function testAccessorMutator()
    {
        $log = new Log(array('extension' => 'log'));

        $this->assertSame('log', $log->getOptions()['extension']);
        $this->assertSame('debug', $log->getLevelThreshold());
        $this->assertSame('info', $log->setLevelThreshold('info')->getLevelThreshold());
        $this->assertSame(null, $log->getLastLine());
        $this->assertSame(0, $log->getLineCount());
        $this->assertSame(sys_get_temp_dir() . '/log_' . date('Y-m-d') . '.log', $log->getLogFilePath());
        $this->assertSame('a', $log->getFileHandleMode());
        $this->assertSame('php://memory', $log->setLogFilePath('php://memory')->getLogFilePath());
        $this->assertSame('w+', $log->getFileHandleMode());
        $this->assertSame('w', $log->setFileHandleMode('w')->getFileHandleMode());
    }

    public function testHelpers()
    {
        $log = new Log(array('extension' => 'log'));

        $this->assertSame(sys_get_temp_dir(), $log->resolveDirectory(null));
        $this->assertSame(TEST_TMP . '/foo', $log->resolveDirectory(TEST_TMP . '/foo/'));
        $this->assertSame('log_' . date('Y-m-d') . '.log', $log->resolveFileName());
        $this->assertSame('daily.txt', (new Log(array('filename' => 'daily')))->resolveFileName());
        $this->assertSame('daily.txt', (new Log(array('filename' => 'daily.txt')))->resolveFileName());
        $this->assertSame('daily.log.txt', (new Log(array('filename' => 'daily.log')))->resolveFileName());
        $this->assertSame(date('Y-m-d'), $log->timestamp('Y-m-d'));
        $this->assertSame('    foo', $log->indent('foo'));
        $this->assertStringContainsString('[warning] foobar', $log->formatMessage(4, 'foobar'));
        $this->assertSame("log error\n    foo: 'bar'\n", (new Log(array('logFormat' => '{message}')))->formatMessage(7, 'log error', array('foo' => 'bar')));
        $this->assertSame("ERROR log error\n", (new Log(array('logFormat' => '{level} {message}')))->formatMessage(3, 'log error'));
        $this->assertSame("UNKNOWN log error\n", (new Log(array('logFormat' => '{level} {message}')))->formatMessage(99, 'log error'));
    }

    public function testLog()
    {
        $log = new Log(array(
            'directory' => 'php://memory',
            'flushFrequency' => 2,
            'logFormat' => '{level} {message}',
            'threshold' => 'error',
        ));

        $this->assertSame('ERROR first log', $log->log('error', 'first log')->getLastLine());
        $this->assertSame('CRITICAL second log', $log->setFileHandleMode('a')->log('critical', 'second log')->getLastLine());
        $this->assertSame('CRITICAL second log', $log->log('debug', 'third log')->getLastLine());
    }

    public function testLogUnwritable()
    {
        $this->expectExceptionMessage('The file could not be written to. Check that appropriate permissions have been set.');

        $log = new Log(array(
            'directory' => 'php://memory',
        ));
        $log->setFileHandleMode('r');
        $log->log('error', 'foo');
    }

    public function testLogDisabling()
    {
        $log = new Log(array(
            'directory' => 'php://memory',
        ));

        $this->assertTrue($log->isEnabled());
        $this->assertFalse($log->isDisabled());
        $this->assertFalse($log->enable()->isDisabled());
        $this->assertTrue($log->disable()->isDisabled());

        $this->assertNull($log->log('error', 'unwriten')->getLastLine());
    }
}
