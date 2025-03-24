<?php

/**
 * Media Chain Addon.
 *
 * @author REDAXO-Team
 * @package redaxo\media-chain
 */

class rex_effect_chain extends rex_effect_abstract
{
    public function execute()
    {
        // Holen der zu verkettenden Typen aus den Parametern
        $chainTypes = explode(',', trim($this->params['types']));
        
        if (empty($chainTypes)) {
            return;
        }
        
        $media = $this->media;
        $originalPath = $media->getMediaPath();
        $tempPath = null;
        
        try {
            foreach ($chainTypes as $typeName) {
                $typeName = trim($typeName);
                
                // Verhindere Endlos-Loops
                if ($typeName == rex_media_manager::getMediaType()) {
                    continue;
                }
                
                // Temporäre Datei für Zwischenschritte
                if ($tempPath === null) {
                    $tempPath = rex_path::addonCache('media_manager', 'chain_' . uniqid() . '.' . $media->getFormat());
                    
                    // Speichere aktuellen Zustand als Datei
                    $imageData = $media->getImage();
                    if ($imageData) {
                        $format = $media->getFormat();
                        $tempImage = null;
                        
                        if ($format == 'jpg' || $format == 'jpeg') {
                            imagejpeg($imageData, $tempPath, 100);
                        } elseif ($format == 'png') {
                            imagepng($imageData, $tempPath, 0);
                        } elseif ($format == 'gif') {
                            imagegif($imageData, $tempPath);
                        } elseif ($format == 'webp' && function_exists('imagewebp')) {
                            imagewebp($imageData, $tempPath, 100);
                        } elseif ($format == 'avif' && function_exists('imageavif')) {
                            imageavif($imageData, $tempPath, 100);
                        } else {
                            // Fallback
                            imagepng($imageData, $tempPath, 0);
                        }
                    } else {
                        // Wenn kein Bild-Objekt vorhanden, original kopieren
                        copy($originalPath, $tempPath);
                    }
                }
                
                // Wende den Typ auf die Zwischendatei an
                $chainedMedia = rex_media_manager::create($typeName, basename($tempPath));
                
                // Speichere das Ergebnis als neue Zwischendatei
                $tempPathNew = rex_path::addonCache('media_manager', 'chain_' . uniqid() . '.' . $chainedMedia->getMedia()->getFormat());
                
                $imageData = $chainedMedia->getMedia()->getImage();
                $format = $chainedMedia->getMedia()->getFormat();
                
                if ($format == 'jpg' || $format == 'jpeg') {
                    imagejpeg($imageData, $tempPathNew, 100);
                } elseif ($format == 'png') {
                    imagepng($imageData, $tempPathNew, 0);
                } elseif ($format == 'gif') {
                    imagegif($imageData, $tempPathNew);
                } elseif ($format == 'webp' && function_exists('imagewebp')) {
                    imagewebp($imageData, $tempPathNew, 100);
                } elseif ($format == 'avif' && function_exists('imageavif')) {
                    imageavif($imageData, $tempPathNew, 100);
                } else {
                    // Fallback
                    imagepng($imageData, $tempPathNew, 0);
                }
                
                // Alte temporäre Datei löschen
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                
                $tempPath = $tempPathNew;
            }
            
            // Abschließend das Ergebnis in das aktuelle Media-Objekt laden
            if ($tempPath !== null && file_exists($tempPath)) {
                // Format bestimmen
                $format = pathinfo($tempPath, PATHINFO_EXTENSION);
                
                // Bild laden
                $finalImage = null;
                if ($format == 'jpg' || $format == 'jpeg') {
                    $finalImage = imagecreatefromjpeg($tempPath);
                } elseif ($format == 'png') {
                    $finalImage = imagecreatefrompng($tempPath);
                    imagealphablending($finalImage, false);
                    imagesavealpha($finalImage, true);
                } elseif ($format == 'gif') {
                    $finalImage = imagecreatefromgif($tempPath);
                } elseif ($format == 'webp' && function_exists('imagecreatefromwebp')) {
                    $finalImage = imagecreatefromwebp($tempPath);
                } elseif ($format == 'avif' && function_exists('imagecreatefromavif')) {
                    $finalImage = imagecreatefromavif($tempPath);
                }
                
                if ($finalImage) {
                    $media->setImage($finalImage);
                    $media->setFormat($format);
                    $media->refreshImageDimensions();
                }
                
                // Temporäre Datei löschen
                unlink($tempPath);
            }
        } catch (Exception $e) {
            // Bei Fehler temporäre Dateien aufräumen
            if ($tempPath !== null && file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            throw $e;
        }
    }
    
    public function getName()
    {
        return rex_i18n::msg('media_manager_effect_chain');
    }
    
    public function getParams()
    {
        return [
            [
                'label' => rex_i18n::msg('media_manager_effect_chain_types'),
                'name' => 'types',
                'type' => 'string',
                'notice' => rex_i18n::msg('media_manager_effect_chain_types_notice')
            ]
        ];
    }
}
