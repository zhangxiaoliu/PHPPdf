<?php

namespace PHPPdf\Test\Glyph\Behaviour;

use PHPPdf\Glyph\Behaviour\StickyNote;

use PHPPdf\Glyph\Container;

class StickyNoteTest extends \TestCase
{
    private $objectMother;
    
    public function init()
    {
        $this->objectMother = new \GenericGlyphObjectMother($this);
    }
    
    /**
     * @test
     */
    public function attachNote()
    {
        $x = 10;
        $y = 200;
        $width = 100;
        $height = 200;
        
        $glyph = $this->getGlyphStub($x, $y, $width, $height);
        
        $gc = $this->getMockBuilder('PHPPdf\Engine\GraphicsContext')
                   ->getMock();
        
       $text = 'some text';

        $gc->expects($this->once())
           ->method('attachStickyNote')
           ->with($x, $y, $x+$width, $y-$height, $text);
           
        $stickyNote = new StickyNote($text);
        
        $stickyNote->attach($gc, $glyph);        
    }
    
    private function getGlyphStub($x, $y, $width, $height)
    {
        $boundary = $this->objectMother->getBoundaryStub($x, $y, $width, $height);
        
        $glyph = new Container();
        $this->invokeMethod($glyph, 'setBoundary', array($boundary));
        
        return $glyph;
    }
}