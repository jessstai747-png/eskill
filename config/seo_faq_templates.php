<?php

return [
    'MLB3530' => [ // Baús e Bagageiros
        [
            'question' => 'Esse {produto} cabe capacete?',
            'answer' => 'Sim! O {produto} comporta 1 capacete fechado tamanho 60.',
            'keywords' => ['capacete', 'cabe']
        ],
        [
            'question' => 'É resistente para delivery?',
            'answer' => 'Sim! Material ABS resistente, ideal para motoboy e entrega delivery.',
            'keywords' => ['resistente', 'delivery', 'motoboy']
        ],
        [
            'question' => 'Serve para viagem?',
            'answer' => 'Perfeito para viagens! {produto} espaçoso e seguro.',
            'keywords' => ['viagem', 'espaçoso', 'seguro']
        ],
        [
            'question' => 'É universal?',
            'answer' => 'Sim! Compatível com Honda CG, Yamaha Fazer e mais de 50 modelos.',
            'keywords' => ['universal', 'compatível', 'honda', 'yamaha']
        ],
        [
            'question' => 'Vem com base de fixação?',
            'answer' => 'Verifique nas especificações se inclui base. Disponível separadamente.',
            'keywords' => ['base', 'fixação', 'instalação']
        ]
    ],
    'MLB1071' => [ // Capacetes
        [
            'question' => 'O {produto} tem certificação de segurança?',
            'answer' => 'Sim! O {produto} atende às normas de segurança vigentes.',
            'keywords' => ['segurança', 'certificação', 'normas']
        ],
        [
            'question' => 'Qual o material do {produto}?',
            'answer' => 'O {produto} é fabricado com material resistente e acabamento durável.',
            'keywords' => ['material', 'resistente', 'durável']
        ],
        [
            'question' => 'Como escolher o tamanho do {produto}?',
            'answer' => 'Consulte a tabela de medidas para garantir o ajuste ideal do {produto}.',
            'keywords' => ['tamanho', 'ajuste', 'medidas']
        ]
    ],
    'default' => [ // Default templates for other categories
        [
            'question' => 'Este {produto} é compatível com outros modelos?',
            'answer' => 'Sim! O {produto} é compatível com uma ampla variedade de modelos.',
            'keywords' => ['compatível', 'modelo']
        ],
        [
            'question' => 'Qual a garantia do {produto}?',
            'answer' => 'O {produto} vem com garantia de fábrica que cobre defeitos de fabricação.',
            'keywords' => ['garantia', 'fábrica']
        ],
        [
            'question' => 'Como instalar o {produto}?',
            'answer' => 'A instalação do {produto} é simples e pode ser feita com ferramentas básicas.',
            'keywords' => ['instalação', 'ferramentas']
        ],
        [
            'question' => 'Quais as dimensões do {produto}?',
            'answer' => 'As dimensões exatas do {produto} estão especificadas nas informações do produto.',
            'keywords' => ['dimensões', 'tamanho', 'especificações']
        ],
        [
            'question' => 'O {produto} é resistente?',
            'answer' => 'Sim! O {produto} é fabricado com materiais resistentes para durabilidade.',
            'keywords' => ['resistente', 'durabilidade', 'qualidade']
        ]
    ]
];