<?php

// Performance and ministry photographs supplied by GODRAM, kept in
// public/images/gallery. Captions describe only what the folder names and
// the pictures themselves show; correct them here without touching code.
$convention = fn ($n, $what) => ['file' => "convention-2026-$n.webp", 'title' => 'Beyond the Room', 'caption' => "Convention 2026 drama ministration: $what", 'year' => 2026];
$outreach = fn ($n, $what) => ['file' => "outreach-$n.webp", 'title' => 'Drama outreach', 'caption' => $what, 'year' => null];
$osun = fn ($n, $what) => ['file' => "osun-conference-$n.webp", 'title' => 'Osun State Conference', 'caption' => $what, 'year' => null];
$stage = fn ($n, $what) => ['file' => "stage-drama-$n.webp", 'title' => 'Stage drama', 'caption' => $what, 'year' => null];

return [
    $convention('5878', 'the market scene'),
    $convention('5832', 'a scene before the floral curtain'),
    $convention('5923', 'the cast on stage'),
    $convention('5887', 'a scene in the village set'),
    $convention('5944', 'the cast together on stage'),
    $convention('5899', 'the market women'),
    $convention('5911', 'a scene on the bench'),
    $convention('5929', 'the cast in song'),
    $convention('5807', 'flags of the nations'),
    $convention('5938', 'the full cast before the congregation'),
    $convention('5947', 'the cast at the close'),
    $convention('5884', 'a scene in the village set'),
    $convention('5905', 'a scene before the congregation'),
    $convention('5914', 'a family scene'),
    $convention('5826', 'a scene on the red stage'),
    $convention('5857', 'a scene before the floral curtain'),
    $convention('5866', 'the waiting scene'),
    $outreach('4731', 'Worship during an outreach'),
    $outreach('4786', 'The crowd at a night outreach'),
    $outreach('4802', 'Worship at a night outreach'),
    $outreach('4819', 'The congregation at an outreach'),
    $outreach('4817', 'Gathered for a night outreach'),
    $osun('5706', 'Delegates in the hall'),
    $osun('5700', 'Young delegates listening'),
    $osun('5716', 'Delegates during a session'),
    $osun('5726', 'A delegate during a session'),
    $osun('5683', 'Young delegates during a session'),
    $osun('5709', 'Delegates during a session'),
    $stage('4415', 'A ministration on stage'),
    $stage('05131', 'Ministers on stage'),
    $stage('05102', 'The cast on stage'),
];
