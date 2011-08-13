<?php

namespace PHPPdf\Test\Glyph\Behaviour;

use PHPPdf\Glyph\Container;

use PHPPdf\Glyph\Behaviour\Bookmark;

class BookmarkTest extends \TestCase
{
    private $objectMother;
    
    public function init()
    {
        $this->objectMother = new \GenericGlyphObjectMother($this);
    }
    
    /**
     * @test
     */
    public function attachBookmarkSingleToGraphicsContexts()
    {
        $name = 'some name';
        $top = 50;
        $bookmark = new Bookmark($name);
        
        $glyph = $this->getGlyphStub(0, $top, 100, 100);
        
        $gc = $this->getMockBuilder('PHPPdf\Engine\GraphicsContext')
                   ->getMock();
        $gc->expects($this->once())
           ->method('addBookmark')
           ->with($bookmark->getUniqueId(), $name, $top);
           
        $bookmark->attach($gc, $glyph);  
        
        //one bookmark may by attached only once
        $bookmark->attach($gc, $glyph);        
    }
    
    private function getGlyphStub($x, $y, $width, $height)
    {
        $boundary = $this->objectMother->getBoundaryStub($x, $y, $width, $height);
        
        $glyph = new Container();
        $this->invokeMethod($glyph, 'setBoundary', array($boundary));
        
        return $glyph;
    }
    
    /**
     * @test
     */
    public function attachNestedBookmarks()
    {
        $parentBookmark = new Bookmark('some name 1');
        
        $parent = $this->getGlyphStub(0, 100, 100, 100);
        $parent->addBehaviour($parentBookmark);
        
        $bookmark = new Bookmark('some name 2');
        $glyph = $this->getGlyphStub(0, 50, 100, 50);
        
        $parent->add($glyph);
        
        $gc = $this->getMockBuilder('PHPPdf\Engine\GraphicsContext')
                   ->getMock();
        $gc->expects($this->once())
           ->method('addBookmark')
           ->with($bookmark->getUniqueId(), $this->anything(), $this->anything(), $parentBookmark->getUniqueId());
           
        $bookmark->attach($gc, $glyph);  
    }
}