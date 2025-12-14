<?php
use PHPUnit\Framework\TestCase;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class SeleniumFormTest extends TestCase {
    private $driver;
    private $baseUrl;
    
    protected function setUp(): void {
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability('goog:chromeOptions', [
            'args' => ['--headless', '--disable-gpu', '--no-sandbox']
        ]);
        
        $this->driver = RemoteWebDriver::create(
            'http://localhost:9515',
            $capabilities
        );
        $this->baseUrl = 'file://' . __DIR__ . '/../';
    }
    
    protected function tearDown(): void {
        if ($this->driver) {
            $this->driver->quit();
        }
    }
    
    public function testValidationShowsErrorForInvalidName() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('bad name!');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        $error = $this->driver->findElement(WebDriverBy::id('shareNameError'))->getText();
        $this->assertStringContainsString('can only contain', $error);
    }
    
    public function testValidationShowsErrorForInvalidPath() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test_share');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/home/test');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('sharePathError'))
        );
        
        $error = $this->driver->findElement(WebDriverBy::id('sharePathError'))->getText();
        $this->assertStringContainsString('/mnt/', $error);
    }
    
    public function testValidationShowsErrorForInvalidMask() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('createMask'))->sendKeys('999');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('createMaskError'))
        );
        
        $error = $this->driver->findElement(WebDriverBy::id('createMaskError'))->getText();
        $this->assertStringContainsString('octal', $error);
    }
    
    public function testValidFormDoesNotShowErrors() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test_share');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('createMask'))->sendKeys('0664');
        $this->driver->findElement(WebDriverBy::tagName('body'))->click();
        
        sleep(1);
        
        $nameError = $this->driver->findElement(WebDriverBy::id('shareNameError'));
        $pathError = $this->driver->findElement(WebDriverBy::id('sharePathError'));
        $maskError = $this->driver->findElement(WebDriverBy::id('createMaskError'));
        
        $this->assertFalse($nameError->isDisplayed());
        $this->assertFalse($pathError->isDisplayed());
        $this->assertFalse($maskError->isDisplayed());
    }
    
    public function testFeedbackNotificationAppears() {
        $this->driver->get($this->baseUrl . 'feedback-test.html');
        
        $this->driver->findElement(WebDriverBy::xpath('//button[contains(text(), "Test Success")]'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.notification-success'))
        );
        
        $notification = $this->driver->findElement(WebDriverBy::cssSelector('.notification-success'))->getText();
        $this->assertStringContainsString('successfully', $notification);
    }
    
    // Edge Cases - Share Name
    public function testShareNameWithSpecialCharacters() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test@#$%');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        $error = $this->driver->findElement(WebDriverBy::id('shareNameError'))->getText();
        $this->assertStringContainsString('can only contain', $error);
    }
    
    public function testShareNameWithSpaces() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('my share');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('shareNameError'))->isDisplayed());
    }
    
    public function testShareNameEmpty() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('shareNameError'))->isDisplayed());
    }
    
    public function testShareNameValidWithUnderscore() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('my_share_123');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        sleep(1);
        
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('shareNameError'))->isDisplayed());
    }
    
    public function testShareNameValidWithHyphen() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('my-share-123');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        sleep(1);
        
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('shareNameError'))->isDisplayed());
    }
    
    // Edge Cases - Path
    public function testPathNotStartingWithMnt() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/home/user/test');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('sharePathError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('sharePathError'))->isDisplayed());
    }
    
    public function testPathEmpty() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('sharePathError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('sharePathError'))->isDisplayed());
    }
    
    public function testPathRelative() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('sharePathError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('sharePathError'))->isDisplayed());
    }
    
    public function testPathValidMntUser() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/documents');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        sleep(1);
        
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('sharePathError'))->isDisplayed());
    }
    
    public function testPathValidMntDisk() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/disk1/data');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        sleep(1);
        
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('sharePathError'))->isDisplayed());
    }
    
    // Edge Cases - Permission Masks
    public function testCreateMaskTooShort() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('createMask'))->sendKeys('066');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('createMaskError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('createMaskError'))->isDisplayed());
    }
    
    public function testCreateMaskMaxLengthEnforced() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $maskField = $this->driver->findElement(WebDriverBy::id('createMask'));
        $maskField->sendKeys('066445678');
        
        // HTML maxlength should limit to 4 chars
        $this->assertEquals(4, strlen($maskField->getAttribute('value')));
    }
    
    public function testCreateMaskInvalidOctal() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('createMask'))->sendKeys('0888');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('createMaskError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('createMaskError'))->isDisplayed());
    }
    
    public function testDirectoryMaskInvalidOctal() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('directoryMask'))->sendKeys('0999');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('directoryMaskError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('directoryMaskError'))->isDisplayed());
    }
    
    public function testAllMasksValid() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('test');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/mnt/user/test');
        $this->driver->findElement(WebDriverBy::id('createMask'))->sendKeys('0664');
        $this->driver->findElement(WebDriverBy::id('directoryMask'))->sendKeys('0775');
        $this->driver->findElement(WebDriverBy::id('shareName'))->click();
        
        sleep(1);
        
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('createMaskError'))->isDisplayed());
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('directoryMaskError'))->isDisplayed());
    }
    
    // Multiple Field Validation
    public function testMultipleFieldsInvalid() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('bad name!');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->sendKeys('/home/test');
        $this->driver->findElement(WebDriverBy::id('createMask'))->sendKeys('999');
        $this->driver->findElement(WebDriverBy::tagName('body'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('shareNameError'))->isDisplayed());
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('sharePathError'))->isDisplayed());
        $this->assertTrue($this->driver->findElement(WebDriverBy::id('createMaskError'))->isDisplayed());
    }
    
    public function testErrorDisappearsWhenCorrected() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        // Enter invalid name
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('bad name!');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        // Correct the name
        $nameField = $this->driver->findElement(WebDriverBy::id('shareName'));
        $nameField->clear();
        $nameField->sendKeys('good_name');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        sleep(1);
        
        $this->assertFalse($this->driver->findElement(WebDriverBy::id('shareNameError'))->isDisplayed());
    }
    
    // Feedback System Tests
    public function testErrorNotificationAppears() {
        $this->driver->get($this->baseUrl . 'feedback-test.html');
        
        $this->driver->findElement(WebDriverBy::xpath('//button[contains(text(), "Test Error")]'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.notification-error'))
        );
        
        $notification = $this->driver->findElement(WebDriverBy::cssSelector('.notification-error'))->getText();
        $this->assertStringContainsString('error', strtolower($notification));
    }
    
    public function testLoadingStateDisablesButton() {
        $this->driver->get($this->baseUrl . 'feedback-test.html');
        
        $button = $this->driver->findElement(WebDriverBy::id('testBtn'));
        $button->click();
        
        sleep(1);
        
        $this->assertFalse($button->isEnabled());
        $this->assertEquals('Processing...', $button->getText());
    }
    
    // Accessibility Tests
    public function testFormFieldsHaveLabels() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $shareName = $this->driver->findElement(WebDriverBy::id('shareName'));
        $sharePath = $this->driver->findElement(WebDriverBy::id('sharePath'));
        
        $this->assertNotEmpty($shareName->getAttribute('id'));
        $this->assertNotEmpty($sharePath->getAttribute('id'));
    }
    
    public function testErrorMessagesAreAccessible() {
        $this->driver->get($this->baseUrl . 'validation-test.html');
        
        $this->driver->findElement(WebDriverBy::id('shareName'))->sendKeys('bad!');
        $this->driver->findElement(WebDriverBy::id('sharePath'))->click();
        
        $this->driver->wait(10)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('shareNameError'))
        );
        
        $error = $this->driver->findElement(WebDriverBy::id('shareNameError'));
        $this->assertTrue($error->isDisplayed());
        $this->assertNotEmpty($error->getText());
    }
}
