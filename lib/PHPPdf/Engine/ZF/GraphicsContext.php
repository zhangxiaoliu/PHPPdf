<?php

/*
 * Copyright 2011 Piotr Śliwa <peter.pl7@gmail.com>
 *
 * License information is in LICENSE file
 */

namespace PHPPdf\Engine\ZF;

use PHPPdf\Exception\Exception,
    PHPPdf\Engine\GraphicsContext as BaseGraphicsContext,
    PHPPdf\Engine\Color as BaseColor,
    PHPPdf\Engine\Font as BaseFont,
    PHPPdf\Engine\Image as BaseImage;

/**
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
class GraphicsContext implements BaseGraphicsContext
{
    private $state = array(
        'fillColor' => null,
        'lineColor' => null,
        'lineWidth' => null,
        'lineDashingPattern' => null,
        'alpha' => 1,
    );
    
    private static $originalState = array(
        'fillColor' => null,
        'lineColor' => null,
        'lineWidth' => null,
        'lineDashingPattern' => null,
        'alpha' => 1,
    );

    private $memento = null;
    
    /**
     * @var Engine
     */
    private $engine = null;

    /**
     * @var \Zend_Pdf_Page
     */
    private $page;
    
    private $methodInvocationsQueue = array();

    public function __construct(Engine $engine, \Zend_Pdf_Page $page)
    {
        $this->engine = $engine;
        $this->page = $page;
    }
    
    public function commit()
    {
        foreach($this->methodInvocationsQueue as $data)
        {
            list($method, $args) = $data;
            call_user_func_array(array($this, $method), $args);
        }
        
        $this->methodInvocationsQueue = array();
    }

    public function getWidth()
    {
        return $this->page->getWidth();
    }

    public function getHeight()
    {
        return $this->page->getHeight();
    }

    public function clipRectangle($x1, $y1, $x2, $y2)
    {
        $this->addToQueue('doClipRectangle', func_get_args());
    }
    
    private function addToQueue($method, array $args)
    {
        $this->methodInvocationsQueue[] = array($method, $args);
    }
    
    protected function doClipRectangle($x1, $y1, $x2, $y2)
    {
        $this->page->clipRectangle($x1, $y1, $x2, $y2);
    }

    public function saveGS()
    {
        $this->addToQueue('doSaveGS', func_get_args());
    }
    
    protected function doSaveGS()
    {
        $this->page->saveGS();
        $this->memento = $this->state;
    }

    public function restoreGS()
    {
        $this->addToQueue('doRestoreGS', func_get_args());
    }
    
    protected function doRestoreGS()
    {
        $this->page->restoreGS();
        $this->state = $this->memento;
        $this->memento = self::$originalState;
    }

    public function drawImage(BaseImage $image, $x1, $y1, $x2, $y2)
    {
        $this->addToQueue('doDrawImage', func_get_args());
    }
    
    protected function doDrawImage(BaseImage $image, $x1, $y1, $x2, $y2)
    {
        $this->page->drawImage($image->getWrappedImage(), $x1, $y1, $x2, $y2);
    }

    public function drawLine($x1, $y1, $x2, $y2)
    {
        $this->addToQueue('doDrawLine', func_get_args());
    }
    
    protected function doDrawLine($x1, $y1, $x2, $y2)
    {
        $this->page->drawLine($x1, $y1, $x2, $y2);
    }

    public function setFont(BaseFont $font, $size)
    {
        $this->addToQueue('doSetFont', func_get_args());

    }
    
    protected function doSetFont(BaseFont $font, $size)
    {
        $fontResource = $font->getCurrentWrappedFont();
        $this->page->setFont($fontResource, $size);
    }

    public function setFillColor($colorData)
    {
        $this->addToQueue('doSetFillColor', func_get_args());
    }
    
    protected function doSetFillColor($colorData)
    {
        $color = $this->getColor($colorData);
        if(!$this->state['fillColor'] || $color->getComponents() !== $this->state['fillColor']->getComponents())
        {
            $this->page->setFillColor($color->getWrappedColor());
            $this->state['fillColor'] = $color;
        }
    }
    
    private function getColor($colorData)
    {
        if(is_string($colorData))
        {
            return $this->engine->createColor($colorData);
        }
        
        if(!$colorData instanceof BaseColor)
        {
            throw new Exception('Wrong color value, expected string or object of PHPPdf\Engine\Color class.');
        }
        
        return $colorData;
    }

    public function setLineColor($colorData)
    {
        $this->addToQueue('doSetLineColor', func_get_args());
    }
    
    protected function doSetLineColor($colorData)
    {
        $color = $this->getColor($colorData);
        if(!$this->state['lineColor'] || $color->getComponents() !== $this->state['lineColor']->getComponents())
        {
            $this->page->setLineColor($color->getWrappedColor());
            $this->state['lineColor'] = $color;
        }
    }

    public function drawPolygon(array $x, array $y, $type)
    {
        $this->addToQueue('doDrawPolygon', func_get_args());
    }
    
    protected function doDrawPolygon(array $x, array $y, $type)
    {
        $this->page->drawPolygon($x, $y, $type);
    }

    public function drawText($text, $x, $y, $encoding, $wordSpacing = 0, $fillType = self::SHAPE_DRAW_FILL)
    {
        $this->addToQueue('doDrawText', func_get_args());
    }
    
    protected function doDrawText($text, $x, $y, $encoding, $wordSpacing = 0, $fillType = self::SHAPE_DRAW_FILL)
    {
        try 
        {
            if($wordSpacing == 0 && $fillType == self::SHAPE_DRAW_FILL)
            {
                $this->page->drawText($text, $x, $y, $encoding);
            }
            else
            {
                $this->richDrawText($text, $x, $y, $encoding, $wordSpacing, $fillType);
            }
        }
        catch(\Zend_Pdf_Exception $e)
        {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
    
    private function richDrawText($text, $x, $y, $encoding, $wordSpacing, $fillType)
    {
        if($this->page->getFont() === null) 
        {
            throw new \Zend_Pdf_Exception('Font has not been set');
        }
  
        if($fillType == self::SHAPE_DRAW_FILL)
        {
            $pdfFillType = 0;
        }
        elseif($fillType == self::SHAPE_DRAW_STROKE)
        {
            $pdfFillType = 1;
        }
        else
        {
            $pdfFillType = 2;
        }       
        
        $data = $this->getDataForTextDrawing($text, $x, $y, $encoding, $wordSpacing, $pdfFillType);

        $this->page->rawWrite($data, 'Text');
    }
    
    private function getDataForTextDrawing($text, $x, $y, $encoding, $wordSpacing, $fillType)
    {
        $font = $this->page->getFont();
        
        $xObj = new \Zend_Pdf_Element_Numeric($x);
        $yObj = new \Zend_Pdf_Element_Numeric($y);
        $wordSpacingObj = new \Zend_Pdf_Element_Numeric($wordSpacing);
        
        $data = "BT\n"
                 .  $xObj->toString() . ' ' . $yObj->toString() . " Td\n"
                 . ($fillType != 0 ? $fillType.' Tr'."\n" : '');
                 
        if($this->isFontDefiningSpaceInSingleByte($font))
        {
            $textObj = $this->createTextObject($font, $text, $encoding);

            $data .= ($wordSpacing != 0 ? $wordSpacingObj->toString().' Tw'."\n" : '')
                     .  $textObj->toString() . " Tj\n";
        }
        //Word spacing form fonts, that defines space char on 2 bytes, dosn't work
        else
        {
            $words = explode(' ', $text);

            $spaceObj = $this->createTextObject($font, ' ', $encoding);
            
            foreach($words as $word)
            {
                $textObj = $this->createTextObject($font, $word, $encoding);
                $data .= '0 Tc'."\n"
                		 . $textObj->toString(). " Tj\n"
                		 . $wordSpacingObj->toString() . " Tc\n"
                		 . $spaceObj->toString() ." Tj\n";
            }
        }
        
        $data .= "ET\n";
                 
        return $data;
    }
    
    private function createTextObject(\Zend_Pdf_Resource_Font $font, $text, $encoding)
    {
        return new \Zend_Pdf_Element_String($font->encodeString($text, $encoding));
    }
    
    private function isFontDefiningSpaceInSingleByte(\Zend_Pdf_Resource_Font $font)
    {
        return $font->getFontType() === \Zend_Pdf_Font::TYPE_STANDARD;
    }

    public function __clone()
    {
        $this->page = clone $this->page;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function drawRoundedRectangle($x1, $y1, $x2, $y2, $radius, $fillType = self::SHAPE_DRAW_FILL_AND_STROKE)
    {
        $this->addToQueue('doDrawRoundedRectangle', func_get_args());
    }
    
    protected function doDrawRoundedRectangle($x1, $y1, $x2, $y2, $radius, $fillType = self::SHAPE_DRAW_FILL_AND_STROKE)
    {
        $this->page->drawRoundedRectangle($x1, $y1, $x2, $y2, $radius, $this->translateFillType($fillType));
    }
    
    private function translateFillType($fillType)
    {
        switch($fillType)
        {
            case self::SHAPE_DRAW_STROKE:
                return \Zend_Pdf_Page::SHAPE_DRAW_STROKE;
            case self::SHAPE_DRAW_FILL:
                return \Zend_Pdf_Page::SHAPE_DRAW_FILL;
            case self::SHAPE_DRAW_FILL_AND_STROKE:
                return \Zend_Pdf_Page::SHAPE_DRAW_FILL_AND_STROKE;
            default:
                throw new \InvalidArgumentException(sprintf('Invalid filling type "%s".', $fillType));
        }
    }

    public function setLineWidth($width)
    {
        $this->addToQueue('doSetLineWidth', func_get_args());
    }
    
    protected function doSetLineWidth($width)
    {
        if(!$this->state['lineWidth'] || $this->state['lineWidth'] != $width)
        {
            $this->page->setLineWidth($width);
            $this->state['lineWidth'] = $width;
        }
    }

    public function setLineDashingPattern($pattern)
    {
        $this->addToQueue('doSetLineDashingPattern', func_get_args());
    }
    
    protected function doSetLineDashingPattern($pattern)
    {
        switch($pattern)
        {
            case self::DASHING_PATTERN_DOTTED:
                $pattern = array(1, 2);
                break;
        }
        
        if($this->state['lineDashingPattern'] === null || $this->state['lineDashingPattern'] !== $pattern)
        {
            $this->page->setLineDashingPattern($pattern);
            $this->state['lineDashingPattern'] = $pattern;
        }
    }
    
    public function uriAction($x1, $y1, $x2, $y2, $uri)
    {
        $this->addToQueue('doUriAction', func_get_args());
    }
    
    protected function doUriAction($x1, $y1, $x2, $y2, $uri)
    {
        try
        {
            $uriAction = \Zend_Pdf_Action_URI::create($uri);
            
            $annotation = $this->createAnnotationLink($x1, $y1, $x2, $y2, $uriAction);
            
            $this->page->attachAnnotation($annotation);
        }
        catch(\Zend_Pdf_Exception $e)
        {
            throw new Exception(sprintf('Error wile adding uri action with uri="%s"', $uri), 0, $e);
        }
    }
    
    public function goToAction(BaseGraphicsContext $gc, $x1, $y1, $x2, $y2, $top)
    {
        $this->addToQueue('doGoToAction', func_get_args());
    }
    
    protected function doGoToAction(BaseGraphicsContext $gc, $x1, $y1, $x2, $y2, $top)
    {
        try
        {
            $destination = \Zend_Pdf_Destination_FitHorizontally::create($gc->getPage(), $top);   
            
            $annotation = $this->createAnnotationLink($x1, $y1, $x2, $y2, $destination);
            
            $this->page->attachAnnotation($annotation);
        }
        catch(\Zend_Pdf_Exception $e)
        {
            throw new Exception('Error while adding goTo action', 0, $e);
        }        
    }
    
    private function createAnnotationLink($x1, $y1, $x2, $y2, $target)
    {
        $annotation = \Zend_Pdf_Annotation_Link::create($x1, $y1, $x2, $y2, $target);
        $annotationDictionary = $annotation->getResource();
        
        $border = new \Zend_Pdf_Element_Array();
        $zero = new \Zend_Pdf_Element_Numeric(0);
        $border->items[] = $zero;
        $border->items[] = $zero;
        $border->items[] = $zero;
        $border->items[] = $zero;
        $annotationDictionary->Border = $border;

        return $annotation;
    }
    
    public function addBookmark($identifier, $name, $top, $parentIdentifier = null)
    {
        $this->addToQueue('doAddBookmark', func_get_args());
    }
    
    protected function doAddBookmark($identifier, $name, $top, $parentIdentifier = null)
    {
        try
        {
            $destination = \Zend_Pdf_Destination_FitHorizontally::create($this->getPage(), $top);
            $action = \Zend_Pdf_Action_GoTo::create($destination);
            
            $outline = \Zend_Pdf_Outline::create($name, $action);
            
            if($parentIdentifier !== null)
            {
                $parent = $this->engine->getOutline($parentIdentifier);
                $parent->childOutlines[] = $outline;
            }
            else
            {
                $this->engine->getZendPdf()->outlines[] = $outline;
            }

            $this->engine->registerOutline($identifier, $outline);            
        }
        catch(\Zend_Pdf_Exception $e)
        {
            throw new Exception('Error while bookmark adding', 0, $e);
        }
    }
    
    public function attachStickyNote($x1, $y1, $x2, $y2, $text)
    {
        $this->addToQueue('doAttachStickyNote', func_get_args());
    }
    
    protected function doAttachStickyNote($x1, $y1, $x2, $y2, $text)
    {
        $annotation = \Zend_Pdf_Annotation_Text::create($x1, $y1, $x2, $y2, $text);
        $this->page->attachAnnotation($annotation);
    }
    
    public function setAlpha($alpha)
    {
        $this->addToQueue('doSetAlpha', func_get_args());
    }
    
    protected function doSetAlpha($alpha)
    {
        if($this->state['alpha'] != $alpha)
        {
            $this->page->setAlpha($alpha);
            $this->state['alpha'] = $alpha;
        }
    }

    public function rotate($x, $y, $angle)
    {
        $this->addToQueue('doRotate', func_get_args());
        
    }
    
    protected function doRotate($x, $y, $angle)
    {
        $this->page->rotate($x, $y, $angle);
    }
    
    public function copy()
    {
        $this->commit();
        $gc = clone $this;
        $gc->page = clone $this->page;
        
        return $gc;
    }
}