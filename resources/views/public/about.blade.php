@php
$timeline = [
    ['1960s to 1980s', 'Films on the road', 'GOFAMINT evangelists from the national headquarters in Ojoo, Ibadan, screened films such as Burning Hell, Ten Commandments, Pilgrim\'s Progress and Believers Heaven at crusades, reaching even the smallest Assemblies. Evangelist Richard Godonu Akapo, a co-founding father and the first General Evangelist, had already adapted his own writings for the stage.'],
    ['Early 1980s', 'The vision returns', 'After an embargo on drama groups, advocates wrote to church leadership asking for drama to be recognised as a ministry. The embargo was partially lifted, and groups flourished in Ife, Iwaya, Mushin, Agege, Ojoo, Ode Aje and on the University of Ife campus.'],
    ['1991', 'GODRAM is born', 'With the permission of the Lagos District Overseer, Pastor S. A. Abiodun, Paul Adaramola began training drama groups in Lagos. This formed the Gospel Drama Ministry (GODRAM), with Paul Adaramola as leader and Paul Alausa as Secretary.'],
    ['18 March 1995', 'The first graduates', 'The GODRAM Institute of Christian Drama (GICD) graduated its first class of 41 drama ministers, after training that began on 29 September 1991 at Ayantuga Assembly, Mushin.'],
    ['1996', 'A national department', 'After the films "Your Choice" and "Ohun Too Yan", GOFAMINT recognised GODRAM as a national department, the GOFAMINT Drama and Film Ministry, with Paul Adaramola as its first Head of Department.'],
    ['1999', 'Stages across Nigeria', 'Bro. Paul Alausa became National Coordinator, leading performances at the National Theatre, Iganmu; Cultural Centre, Mokola, Ibadan; Orokpotha Hall, Benin; Cultural Centre, Port Harcourt; and GOFAMINT Nyanya, Abuja.'],
    ['2009', 'For the sake of peace', 'Pastor Paul Adaramola, who returned to GOFAMINT in 2005, was reappointed National GODRAM Pastor as Bro. Paul Alausa stepped down to promote peace within the ministry.'],
    ['March 2021', 'A new season', 'Pastor Paul Adaramola was redeployed as National Evangelist to Region 1, and Pastor Paul Alausa was reappointed National Coordinator and Head of Department.'],
];
$films = ['Your Choice / Ohun Too Yan', 'A Little While / Ni\'gba Die Sii', 'God of Vengeance / Olorun Esan', 'No Second Chance / Ko S\'Aaye Eekeji', 'Born to Reign / Omo Alase', 'Valley of Baca / Afonifoji Omije', 'Valley de Ba\'ca / Dasi Vive', 'Terminated', 'Die Alone', 'On the Rock / L\'ori Apata', 'Shadow of the Almighty / Ojiji Olodumare', 'Attention / Aaye', 'The Chronicle', 'L\'Oracle'];
$colleges = [
    ['GICD', 'GODRAM Institute of Christian Drama'],
    ['GIBICODE', 'GODRAM Interdenominational Bible College of Drama/Film Evangelism'],
    ['GMC', 'GODRAM Media College'],
    ['GONACIC', 'National Cinematography Induction Course'],
    ['MTS', 'GODRAM Mobile Training Scheme'],
];
@endphp
<x-layouts.app title="About GODRAM" description="The story of the GOFAMINT Drama & Film Ministry, from film outreaches in the 1960s to a national department and beyond.">
    <x-hero page="about" eyebrow="About GODRAM" lead="GODRAM is the Drama & Film Ministry of the Gospel Faith Mission International. Through live drama, films, photo drama and training, it has planted churches, restored homes and raised a generation of creative ministers.">
        <x-slot:heading>
            <h1 class="hero-title mt-3 text-4xl sm:text-6xl">Drama and film<br>for the Gospel</h1>
        </x-slot:heading>
    </x-hero>

    <section class="container-page mt-14 grid gap-10 lg:grid-cols-[2fr_1fr]">
        <div>
            <h2 class="h-section text-2xl">Our story</h2>
            <ol class="relative mt-6 border-l-2 border-poster/40 pl-6">
                @foreach ($timeline as [$when, $title, $text])
                    <li class="mb-8 last:mb-0">
                        <span class="absolute -left-[9px] mt-1.5 size-4 rounded-full border-2 border-paper bg-curtain" aria-hidden="true"></span>
                        <p class="font-display text-sm font-semibold uppercase tracking-wider text-poster">{{ $when }}</p>
                        <h3 class="mt-0.5 font-display text-xl font-semibold uppercase text-stage">{{ $title }}</h3>
                        <p class="mt-1.5 text-ink-soft">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
        <aside class="space-y-6">
            <div class="card-pad">
                <h2 class="h-section">What GODRAM does</h2>
                <ul class="mt-3 space-y-2 text-sm text-ink-soft">
                    <li><span class="font-semibold text-ink">Outreaches:</span> live drama ministrations, film outreaches, photo drama and drama magazines.</li>
                    <li><span class="font-semibold text-ink">GACASA:</span> the GODRAM Annual Conference of All Saint Artistes.</li>
                    <li><span class="font-semibold text-ink">GONAPRET:</span> the GODRAM National Annual Prayer Retreat.</li>
                    <li><span class="font-semibold text-ink">Partnership:</span> member of the All Nigeria Conference of Evangelical Drama Ministers (ANCEDRAM).</li>
                </ul>
            </div>
            <div class="card-pad">
                <h2 class="h-section">Training colleges</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($colleges as [$abbr, $name])
                        <li><span class="font-display font-semibold text-curtain">{{ $abbr }}</span> <span class="text-ink-soft">{{ $name }}</span></li>
                    @endforeach
                </ul>
            </div>
        </aside>
    </section>

    <section class="mt-14 bg-paper-2 py-12">
        <div class="container-page">
            <h2 class="h-section text-2xl">Films</h2>
            <p class="mt-1 text-ink-soft">Produced in English, Yoruba, French and Fon, alongside many films by GODRAM college students, states, regions and districts.</p>
            <ul class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($films as $film)
                    <li class="flex items-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-medium"><x-icon name="film" class="size-4 text-poster" />{{ $film }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="container-page mt-14">
        <h2 class="h-section text-2xl">Impact on GOFAMINT</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Churches planted through drama and film, many now District and Regional headquarters.',
                'Lives transformed and future ministers nurtured.',
                'Talented young people guided to use their gifts for God.',
                'Broken homes restored through anointed ministrations.',
                'GOFAMINT\'s history documented through The Chronicle.',
                'Miracles of healing and breakthrough witnessed at ministrations.',
            ] as $item)
                <div class="card-pad text-ink-soft"><x-icon name="check" class="mb-2 size-5 text-ok" />{{ $item }}</div>
            @endforeach
        </div>
        <div class="mt-10 flex flex-wrap gap-3">
            <a href="{{ route('archive') }}" class="btn-dark no-underline">See the archive</a>
            <a href="{{ route('network') }}" class="btn-ghost no-underline">Explore the GODRAM network</a>
        </div>
    </section>
</x-layouts.app>
