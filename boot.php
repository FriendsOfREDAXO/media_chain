<?php
// Den Chain-Effekt beim Media Manager registrieren
if (rex_addon::get('media_manager')->isAvailable()) {
    rex_media_manager::addEffect('rex_effect_chain');
}
