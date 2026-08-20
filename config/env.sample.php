<?php
/**
 * RE360 — optional environment override.
 *
 * You normally do NOT need this file. config/config.php decides on its own:
 * a real domain runs in production, localhost runs in development.
 *
 * Copy this to config/env.php only to force a mode — for example to read a
 * full error trace on the live server. DELETE config/env.php afterwards;
 * leaving it on 'development' exposes PHP errors to every visitor.
 */

$RE360_ENV = 'development';   // 'development' | 'production'
