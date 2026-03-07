<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 🔍 Title Attribute Extractor Service
 * 
 * Extrai atributos de produtos a partir do título usando:
 * - Regex patterns
 * - Dicionários de marcas/modelos
 * - Normalização de valores
 * 
 * @version 1.0.0
 */
class TitleAttributeExtractorService
{
    /**
     * Dicionário de marcas conhecidas por categoria
     * Expandir conforme necessário
     */
    /**
     * Dicionário de marcas conhecidas por categoria
     * Total: 1000+ marcas organizadas por categoria
     * Última atualização: 2025-01
     */
    private array $brandDictionary = [
        // =====================================================
        // ELETRÔNICOS (200+ marcas)
        // =====================================================
        'electronics' => [
            // Smartphones & Tablets
            'Samsung', 'Apple', 'Motorola', 'Xiaomi', 'Huawei', 'OnePlus', 'Oppo',
            'Realme', 'Vivo', 'Nokia', 'Google', 'Honor', 'Poco', 'Redmi', 'Nothing',
            'ZTE', 'TCL', 'Alcatel', 'Positivo', 'Multilaser', 'LG', 'HTC', 'BlackBerry',
            'Meizu', 'Infinix', 'Tecno', 'Itel', 'Umidigi', 'Doogee', 'Ulefone', 'Cubot',
            'Oukitel', 'Blackview', 'AGM', 'CAT', 'Lenovo',
            // Áudio & Fones
            'JBL', 'Bose', 'Sennheiser', 'Sony', 'Audio-Technica', 'Beyerdynamic', 'AKG',
            'Shure', 'Skullcandy', 'Beats', 'Jabra', 'Plantronics', 'Bang & Olufsen', 'B&O',
            'Marshall', 'Harman Kardon', 'Edifier', 'Creative', 'Anker', 'Soundcore',
            'QCY', 'Haylou', 'KZ', 'Moondrop', 'FiiO', 'Topping', 'SMSL', 'Tribit',
            'Earfun', 'SoundPeats', 'Liberty', 'Baseus', 'Tranya', 'MPOW', 'TaoTronics',
            'Aukey', 'Bluedio', '1More', 'Focal', 'Grado', 'Meze', 'HiFiMan', 'Audeze',
            // TVs & Monitores
            'LG', 'Samsung', 'Sony', 'TCL', 'Philips', 'AOC', 'Philco', 'Panasonic',
            'Hisense', 'Toshiba', 'Sharp', 'Semp', 'Multilaser', 'BenQ', 'ViewSonic',
            'Dell', 'HP', 'Asus', 'Acer', 'Gigabyte', 'MSI', 'LG UltraGear', 'Alienware',
            'PRISM+', 'Eve', 'Xiaomi Mi', 'Redmi', 'OnePlus', 'Realme',
            // Câmeras & Foto
            'Canon', 'Nikon', 'Sony', 'Fujifilm', 'Panasonic', 'Olympus', 'Pentax',
            'Leica', 'Hasselblad', 'Phase One', 'GoPro', 'DJI', 'Insta360', 'Ricoh',
            'Kodak', 'Polaroid', 'Instax', 'Sigma', 'Tamron', 'Tokina', 'Samyang',
            'Viltrox', 'Godox', 'Yongnuo', 'Neewer', 'Manfrotto', 'Joby', 'Peak Design',
            // Periféricos Gaming
            'Logitech', 'Razer', 'Corsair', 'HyperX', 'SteelSeries', 'ASUS ROG',
            'MSI', 'Gigabyte Aorus', 'Cooler Master', 'Thermaltake', 'NZXT',
            'Glorious', 'Ducky', 'Keychron', 'Anne Pro', 'Akko', 'Leopold', 'Varmilo',
            'Drop', 'GMMK', 'Redragon', 'T-Dagger', 'Havit', 'Fantech', 'Marvo',
            'Trust', 'Genesis', 'Sharkoon', 'Roccat', 'MadCatz', 'Turtle Beach',
            'Astro', 'EPOS', 'Fnatic', 'Xtrfy', 'Pulsar', 'Zowie', 'Endgame Gear',
            // Consoles & Gaming
            'Sony PlayStation', 'Microsoft Xbox', 'Nintendo', '8BitDo', 'Hori',
            'PowerA', 'SCUF', 'Nacon', 'Thrustmaster', 'Fanatec', 'Logitech G',
            // Wearables & Smartwatch
            'Apple Watch', 'Samsung Galaxy Watch', 'Garmin', 'Fitbit', 'Amazfit',
            'Huawei Watch', 'Xiaomi Mi Band', 'Withings', 'Polar', 'Suunto',
            'Coros', 'Mobvoi', 'Fossil', 'TicWatch', 'Zepp', 'Realme Band',
            // Smart Home
            'Google Nest', 'Amazon Echo', 'Ring', 'Arlo', 'Wyze', 'Eufy', 'TP-Link',
            'Tapo', 'Philips Hue', 'LIFX', 'Nanoleaf', 'Govee', 'Yeelight', 'Sonoff',
            'Tuya', 'Shelly', 'Aqara', 'SmartThings', 'Honeywell', 'Ecobee', 'Nest',
        ],
        
        // =====================================================
        // ELETRODOMÉSTICOS (150+ marcas)
        // =====================================================
        'appliances' => [
            // Linha Branca
            'Electrolux', 'Brastemp', 'Consul', 'LG', 'Samsung', 'Panasonic', 'Midea',
            'Philco', 'Britânia', 'Mondial', 'Continental', 'GE', 'Bosch', 'Siemens',
            'Whirlpool', 'Miele', 'Gorenje', 'Liebherr', 'Sub-Zero', 'Viking', 'KitchenAid',
            'Frigidaire', 'Maytag', 'Amana', 'Dako', 'Mueller', 'Suggar', 'Atlas',
            'Esmaltec', 'Venax', 'Geladeira', 'Freezer', 'Lava-Louças',
            // Pequenos Eletrodomésticos
            'Arno', 'Oster', 'Cadence', 'Philips', 'Black+Decker', 'Cuisinart',
            'Ninja', 'Nutribullet', 'Vitamix', 'Blendtec', 'Magic Bullet', 'Hamilton Beach',
            'Krups', 'DeLonghi', 'Nespresso', 'Dolce Gusto', 'Cafeteira', 'Três Corações',
            'Tramontina', 'Rochedo', 'Brinox', 'Coza', 'Oxford', 'Nadir Figueiredo',
            // Limpeza & Aspiradores
            'Electrolux', 'Dyson', 'Shark', 'Bissell', 'Hoover', 'Wap', 'Karcher',
            'Philco', 'Britânia', 'Mondial', 'Midea', 'Rowenta', 'Polti', 'Vax',
            'Roborock', 'iRobot', 'Roomba', 'Ecovacs', 'Deebot', 'Xiaomi', 'Dreame',
            // Climatização
            'Consul', 'Midea', 'LG', 'Samsung', 'Carrier', 'Springer', 'Daikin',
            'Fujitsu', 'Hitachi', 'Gree', 'Elgin', 'Komeco', 'Philco', 'Britânia',
            'Ventisol', 'Arno', 'Mondial', 'Cadence', 'Mallory', 'Wap',
            // Eletroportáteis
            'Philips', 'Remington', 'Babyliss', 'Conair', 'GA.MA', 'Taiff', 'Mondial',
            'Philco', 'Cadence', 'Britânia', 'Lizz', 'MQ', 'Mega', 'Kiss NY', 'Vertix',
            'Wahl', 'Panasonic', 'Braun', 'Andis', 'Oster',
        ],
        
        // =====================================================
        // MODA (200+ marcas)
        // =====================================================
        'fashion' => [
            // Esportivo
            'Nike', 'Adidas', 'Puma', 'Reebok', 'New Balance', 'Asics', 'Mizuno',
            'Under Armour', 'Olympikus', 'Fila', 'Kappa', 'Diadora', 'Umbro', 'Lotto',
            'Skechers', 'Converse', 'Vans', 'Champion', 'Jordan', 'Yeezy', 'Brooks',
            'Saucony', 'Hoka', 'On Running', 'Salomon', 'Merrell', 'Columbia', 'The North Face',
            'Patagonia', 'Arc\'teryx', 'Mammut', 'Oakley', 'Speedo', 'Arena', 'TYR',
            // Casual Premium
            'Lacoste', 'Tommy Hilfiger', 'Calvin Klein', 'Ralph Lauren', 'Polo',
            'Hugo Boss', 'Armani', 'Emporio Armani', 'Diesel', 'Guess', 'Levi\'s',
            'GAP', 'Banana Republic', 'Abercrombie', 'Hollister', 'American Eagle',
            'Timberland', 'Dockers', 'Nautica', 'GANT', 'Izod', 'US Polo', 'Wrangler',
            'Lee', 'Carhartt', 'Dickies', 'Ben Sherman', 'Fred Perry', 'Superdry',
            // Fast Fashion Brasil
            'Hering', 'Reserva', 'Colcci', 'Zara', 'H&M', 'C&A', 'Renner', 'Riachuelo',
            'Marisa', 'Lojas Americanas', 'Leader', 'Torra', 'Besni', 'Pernambucanas',
            'Malwee', 'Lunender', 'Morena Rosa', 'John John', 'Farm', 'Animale',
            'Le Lis Blanc', 'Bo.Bô', 'Shoulder', 'Maria Filó', 'Ateen', 'A.Brand',
            'Zinzane', 'Cantão', 'Dzarm', 'Triton', 'Forum', 'Tufi Duek', 'Ellus',
            // Calçados
            'Arezzo', 'Schutz', 'Santa Lolla', 'Vizzano', 'Via Marte', 'Ramarim',
            'Dakota', 'Beira Rio', 'Piccadilly', 'Usaflex', 'Moleca', 'Modare',
            'Ferracini', 'Democrata', 'Samello', 'West Coast', 'Pegada', 'Free Way',
            'Opananken', 'Kildare', 'Vulcabras', 'Havaianas', 'Ipanema', 'Grendene',
            'Rider', 'Cartago', 'Kenner', 'Crocs', 'Birkenstock', 'Dr. Martens',
            // Luxo
            'Louis Vuitton', 'Gucci', 'Prada', 'Chanel', 'Dior', 'Versace', 'Dolce & Gabbana',
            'Burberry', 'Balenciaga', 'Givenchy', 'Saint Laurent', 'YSL', 'Bottega Veneta',
            'Fendi', 'Valentino', 'Alexander McQueen', 'Off-White', 'Moncler', 'Stone Island',
            // Infantil
            'Tip Top', 'Brandili', 'Kyly', 'Alakazoo', 'Lilica Ripilica', 'Tigor',
            'PUC', 'Green', 'Milon', 'Mundi', 'Colorittá', 'Marisol',
        ],
        
        // =====================================================
        // INFORMÁTICA (150+ marcas)
        // =====================================================
        'computers' => [
            // Processadores
            'Intel', 'AMD', 'Qualcomm', 'MediaTek', 'ARM',
            // Placas de Vídeo
            'Nvidia', 'AMD Radeon', 'EVGA', 'Gigabyte', 'Asus', 'MSI', 'Zotac',
            'Galax', 'PNY', 'Palit', 'Gainward', 'Inno3D', 'PowerColor', 'XFX',
            'Sapphire', 'ASRock', 'Colorful', 'KFA2',
            // Placas-mãe
            'Asus', 'MSI', 'Gigabyte', 'ASRock', 'EVGA', 'Biostar', 'NZXT',
            'Colorful', 'Supermicro', 'Intel', 'Foxconn',
            // Memória RAM
            'Corsair', 'Kingston', 'Crucial', 'G.Skill', 'Team', 'Patriot',
            'Adata', 'HyperX', 'Mushkin', 'PNY', 'GeIL', 'XPG', 'Asgard',
            'Lexar', 'Silicon Power', 'V-Color', 'OLOy', 'Neo Forza', 'Klevv',
            // Armazenamento
            'WD', 'Western Digital', 'Seagate', 'Samsung', 'Toshiba', 'HGST', 'Hitachi',
            'Kingston', 'Crucial', 'SanDisk', 'Adata', 'Corsair', 'Sabrent', 'Inland',
            'SK Hynix', 'Micron', 'Intel Optane', 'Phison', 'Silicon Motion',
            'Lexar', 'PNY', 'Team', 'XPG', 'Patriot', 'Mushkin', 'Plextor',
            // Gabinetes & Fontes
            'Corsair', 'NZXT', 'Cooler Master', 'Thermaltake', 'Be Quiet', 'Fractal Design',
            'Lian Li', 'Phanteks', 'EVGA', 'Seasonic', 'Super Flower', 'FSP', 'XPG',
            'Silverstone', 'In Win', 'Cougar', 'Antec', 'Deepcool', 'Redragon',
            'Aerocool', 'Gamemax', 'Montech', 'Gamdias', 'Segotep', 'Sama',
            // Refrigeração
            'Noctua', 'Be Quiet', 'Cooler Master', 'Corsair', 'NZXT', 'Deepcool',
            'Arctic', 'Scythe', 'Thermalright', 'ID-Cooling', 'Cryorig', 'Thermaltake',
            'EKWB', 'Alphacool', 'Aquacomputer', 'Bitspower', 'Barrow', 'Bykski',
            // Notebooks & PCs
            'Dell', 'HP', 'Lenovo', 'Acer', 'Asus', 'MSI', 'Apple', 'Microsoft Surface',
            'Razer', 'Alienware', 'Samsung', 'LG', 'Huawei', 'Xiaomi', 'Positivo',
            'Vaio', 'Toshiba', 'Avell', 'Multilaser', 'Compaq', 'Gateway', 'eMachines',
            // Redes & Conectividade
            'TP-Link', 'Netgear', 'Asus', 'D-Link', 'Linksys', 'Ubiquiti', 'UniFi',
            'MikroTik', 'Cisco', 'Aruba', 'Ruckus', 'Intelbras', 'Multilaser', 'Mercusys',
            'Tenda', 'Xiaomi Mi', 'Google Nest', 'Eero', 'Orbi', 'AmpliFi',
        ],
        
        // =====================================================
        // AUTOMOTIVO (100+ marcas)
        // =====================================================
        'automotive' => [
            // Peças & Componentes
            'Bosch', 'NGK', 'Denso', 'Valeo', 'Magneti Marelli', 'Delphi', 'ACDelco',
            'Continental', 'ZF', 'Schaeffler', 'INA', 'FAG', 'SKF', 'NTN', 'Timken',
            'TRW', 'Monroe', 'Sachs', 'KYB', 'Cofap', 'Nakata', 'Axios', 'Perfect',
            'Genuíno', 'Original', 'Wega', 'Mahle', 'Metal Leve', 'Varga', 'Fras-le',
            'Cobreq', 'Jurid', 'Fremax', 'Hipper Freios', 'SYL', 'Urba', 'Gates',
            'Dayco', 'Goodyear', 'Continental', 'SKF', 'INA', 'Ruville', 'Febi',
            // Pneus
            'Bridgestone', 'Pirelli', 'Michelin', 'Goodyear', 'Continental', 'Firestone',
            'Dunlop', 'Yokohama', 'Hankook', 'Kumho', 'Nexen', 'Toyo', 'Falken',
            'BFGoodrich', 'Cooper', 'General Tire', 'Nankang', 'Achilles', 'Ling Long',
            'Westlake', 'Triangle', 'Sailun', 'GT Radial', 'Federal', 'Maxxis',
            // Lubrificantes & Fluidos
            'Mobil', 'Shell', 'Castrol', 'Petronas', 'Liqui Moly', 'Motul', 'Valvoline',
            'Elf', 'Total', 'Gulf', 'Pennzoil', 'Quaker State', 'Royal Purple', 'AMSOIL',
            'Bardahl', 'Ipiranga', 'Lubrax', 'Texaco', 'Havoline', 'Selênia', 'Tutela',
            // Som & Acessórios
            'Pioneer', 'JBL', 'Sony', 'Kenwood', 'Alpine', 'JVC', 'Blaupunkt',
            'Clarion', 'Boss', 'Taramps', 'Stetsom', 'Banda', 'Soundigital',
            'Roadstar', 'Multilaser', 'Positron', 'H-Tech', 'Hertz', 'Focal',
            // Iluminação
            'Philips', 'Osram', 'GE', 'Hella', 'Bosch', 'Wagner', 'Sylvania',
            'Narva', 'H7', 'H4', 'H1', 'Xenon', 'LED', 'HID', 'Eagle Eyes',
            // Montadoras (para peças compatíveis)
            'Volkswagen', 'VW', 'Fiat', 'Chevrolet', 'GM', 'Ford', 'Toyota', 'Honda',
            'Hyundai', 'Kia', 'Nissan', 'Renault', 'Peugeot', 'Citroën', 'Jeep',
            'Mercedes', 'BMW', 'Audi', 'Volvo', 'Land Rover', 'Mitsubishi', 'Suzuki',
        ],
        
        // =====================================================
        // MOTOS - NOVA CATEGORIA (200+ marcas)
        // =====================================================
        'motos' => [
            // Montadoras de Motos
            'Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'Dafra', 'Shineray', 'Haojue',
            'Kasinski', 'BMW Motorrad', 'Triumph', 'Ducati', 'Harley-Davidson', 'Harley',
            'KTM', 'Royal Enfield', 'Indian', 'Aprilia', 'MV Agusta', 'Benelli',
            'Moto Guzzi', 'Husqvarna', 'CFMoto', 'SYM', 'Kymco', 'Piaggio', 'Vespa',
            'Beta', 'GasGas', 'TM Racing', 'Husaberg', 'Sherco', 'Rieju', 'Derbi',
            // Capacetes
            'AGV', 'Shoei', 'Arai', 'Bell', 'HJC', 'Shark', 'Nolan', 'X-Lite',
            'Schuberth', 'LS2', 'MT Helmets', 'Airoh', 'Scorpion', 'Icon', 'Simpson',
            'Biltwell', 'Ruroc', 'Caberg', 'Origine', 'Premier', 'Suomy', 'Vemar',
            'Givi', 'Helt', 'Pro Tork', 'Norisk', 'Taurus', 'EBF', 'Kraft',
            'Bieffe', 'Fly', 'San Marino', 'New', 'Mixs', 'Peels', 'Axxis',
            'Texx', 'Nasa', 'Liberty', 'Lucca', 'ASX', 'Motociclista', 'Fechado',
            // Jaquetas & Vestimentas
            'Alpinestars', 'Dainese', 'Rev\'it', 'Spidi', 'Held', 'Rukka', 'Klim',
            'Shoei', 'RST', 'Oxford', 'Richa', 'Bering', 'Furygan', 'Ixon',
            'Segura', 'Modeka', 'Büse', 'Stadler', 'IXS', 'Macna', 'Seventy Degrees',
            'Texx', 'X11', 'Claw', 'Forza', 'Riffel', 'Tutto Moto', 'Pantaneiro',
            // Luvas
            'Alpinestars', 'Dainese', 'Rev\'it', 'Held', 'Five', 'Racer',
            'Knox', 'Lee Parks', 'Cortech', 'Joe Rocket', 'Scorpion', 'Icon',
            'Texx', 'X11', 'Tutto', 'Claw', 'Riffel', 'Free Hands',
            // Botas & Calçados
            'Alpinestars', 'Dainese', 'Sidi', 'Gaerne', 'TCX', 'Forma',
            'Stylmartin', 'XPD', 'Falco', 'Rev\'it', 'Bering', 'RST',
            'Texx', 'Motoqueiro', 'Acero', 'Atron', 'Mondeo', 'Boots',
            // Peças & Acessórios
            'Pro Tork', 'Dianteiro', 'Traseiro', 'Circuit', 'Renthal', 'Pro Taper',
            'Acerbis', 'Polisport', 'UFO', 'Racetech', 'Twin Air', 'K&N',
            'Yoshimura', 'Akrapovic', 'Termignoni', 'Arrow', 'LeoVince', 'SC Project',
            'FMF', 'Two Brothers', 'Remus', 'GPR', 'IXIL', 'Spark', 'Mivv',
            'Rizoma', 'Gilles', 'CNC Racing', 'LighTech', 'Evotech', 'R&G',
            'Puig', 'MRA', 'Zero Gravity', 'Ermax', 'National Cycle', 'Givi',
            'SW-Motech', 'Kappa', 'Shad', 'Top Case', 'Baú', 'Alforge', 'Bolsa',
            // Pneus Moto
            'Pirelli', 'Michelin', 'Bridgestone', 'Dunlop', 'Metzeler', 'Continental',
            'Maxxis', 'IRC', 'Shinko', 'Avon', 'Kenda', 'Duro', 'Technic',
            'Maggion', 'Rinaldi', 'Levorin', 'Vipal', 'Titan', 'Pilot',
            // Óleos & Lubrificantes Moto
            'Motul', 'Liqui Moly', 'Castrol Power', 'Shell Advance', 'Mobil 1', 'Yamalube',
            'Honda Genuine', 'Kawasaki', 'Repsol', 'Ipiranga Moto', 'Bardahl', 'Petronas',
            // Freios & Suspensão
            'Brembo', 'Nissin', 'Galfer', 'EBC', 'Ferodo', 'SBS', 'Bendix', 'Vesrah',
            'Öhlins', 'Showa', 'Kayaba', 'WP', 'Marzocchi', 'Bitubo', 'Mupo', 'Matris',
            // Correntes & Transmissão
            'DID', 'RK', 'EK', 'Regina', 'Choho', 'JT Sprockets', 'Sunstar', 'AFAM',
            'Renthal', 'Pro Taper', 'ZF', 'Vortex', 'PBR', 'Supersprox',
            // Elétrica & Iluminação
            'NGK', 'Denso', 'Iridium', 'Yuasa', 'Moura', 'Heliar', 'Brandy', 'Route',
            'Philips', 'Osram', 'LED', 'HID', 'Xenon', 'Farol', 'Lanterna', 'Pisca',
            // Manetes & Comandos
            'CRG', 'Brembo', 'Magura', 'Domino', 'Quick Action', 'Accossato',
            // Marcas BR populares
            'BR Parts', 'Cofap', 'Nakata', 'Dafra Parts', 'Honda Genuíno', 'Yamaha Original',
        ],
        
        // =====================================================
        // FERRAMENTAS & CONSTRUÇÃO (100+ marcas)
        // =====================================================
        'tools' => [
            // Ferramentas Elétricas
            'Bosch', 'Makita', 'DeWalt', 'Milwaukee', 'Black+Decker', 'Stanley',
            'Skil', 'Dremel', 'Hitachi', 'Hikoki', 'Metabo', 'Festool', 'Hilti',
            'Einhell', 'Ryobi', 'Worx', 'Craftsman', 'Ridgid', 'Porter-Cable',
            'Tramontina', 'Vonder', 'Gamma', 'Nove54', 'Ferrari', 'Motomil',
            'Nagano', 'Lynus', 'Schulz', 'Pressure', 'Chiaperini', 'Somar',
            // Ferramentas Manuais
            'Tramontina', 'Gedore', 'Stanley', 'Irwin', 'Starrett', 'Mitutoyo',
            'King Tony', 'Belzer', 'Brasfort', 'Vonder', 'EDA', 'Worker',
            // Jardinagem
            'Stihl', 'Husqvarna', 'Tramontina', 'Trapp', 'Garthen', 'Toyama',
            'Branco', 'Kawashima', 'Tekna', 'Vulcan', 'Nagano', 'Matsuyama',
        ],
        
        // =====================================================
        // BELEZA & COSMÉTICOS (100+ marcas)
        // =====================================================
        'beauty' => [
            // Cabelo
            'L\'Oréal', 'Wella', 'Schwarzkopf', 'Redken', 'Kerastase', 'Matrix',
            'Joico', 'Alfaparf', 'Inoar', 'Cadiveu', 'Bio Extratus', 'Salon Line',
            'Lola Cosmetics', 'Novex', 'Embelleze', 'Skala', 'Yamasterol', 'Haskell',
            'Forever Liss', 'Truss', 'Felps', 'Richée', 'Agi Max', 'Mutari', 'Soul Power',
            // Maquiagem
            'MAC', 'Maybelline', 'L\'Oréal', 'Revlon', 'Avon', 'Natura', 'O Boticário',
            'Vult', 'Ruby Rose', 'Dailus', 'Quem Disse Berenice', 'Eudora', 'Mary Kay',
            'Urban Decay', 'NYX', 'Fenty Beauty', 'Charlotte Tilbury', 'Too Faced',
            'Tarte', 'Benefit', 'Nars', 'Rare Beauty', 'Anastasia', 'Huda Beauty',
            // Skincare
            'La Roche-Posay', 'Vichy', 'Bioderma', 'Cerave', 'Neutrogena', 'ROC',
            'Clinique', 'Estée Lauder', 'Lancôme', 'Kiehl\'s', 'The Ordinary', 'Drunk Elephant',
            'Skinceuticals', 'Dermalogica', 'Paula\'s Choice', 'Tatcha', 'Glow Recipe',
            // Perfumaria
            'Natura', 'O Boticário', 'Eudora', 'L\'Bel', 'Avon', 'Jequiti', 'Hinode',
            'Dior', 'Chanel', 'Yves Saint Laurent', 'Givenchy', 'Versace', 'Dolce & Gabbana',
            'Carolina Herrera', 'Paco Rabanne', 'Jean Paul Gaultier', 'Hugo Boss', 'Armani',
        ],
        
        // =====================================================
        // BRINQUEDOS & INFANTIL (80+ marcas)
        // =====================================================
        'toys' => [
            'LEGO', 'Mattel', 'Hasbro', 'Fisher-Price', 'Playmobil', 'Hot Wheels',
            'Barbie', 'Nerf', 'Play-Doh', 'My Little Pony', 'Transformers', 'Marvel',
            'Star Wars', 'Disney', 'Pixar', 'Nintendo', 'Funko', 'Bandai',
            'Estrela', 'Grow', 'Copag', 'Toyster', 'Polibrinq', 'Candide',
            'Brinquedos Cardoso', 'Roma', 'Elka', 'Cotiplás', 'Rosita', 'Dismat',
            'Xalingo', 'Pais & Filhos', 'Brink+', 'Zippy Toys', 'Samba Toys',
            'Baby Alive', 'LOL Surprise', 'Paw Patrol', 'PJ Masks', 'Peppa Pig',
        ],
        
        // =====================================================
        // ESPORTE & FITNESS (80+ marcas)
        // =====================================================
        'sports' => [
            // Fitness & Academia
            'Nike', 'Adidas', 'Puma', 'Under Armour', 'Reebok', 'Everlast',
            'Venum', 'UFC', 'Tapout', 'Pretorian', 'Rudel', 'Vollo',
            'Polimet', 'Movement', 'Kikos', 'Athletic', 'Speedo', 'Arena',
            'TYR', 'Hammerhead', 'Mormaii', 'Oakley', 'HB', 'Olympikus',
            // Bicicletas
            'Caloi', 'Monark', 'Sense', 'Oggi', 'TSW', 'Specialized', 'Trek',
            'Giant', 'Cannondale', 'Scott', 'Merida', 'GT', 'BMC', 'Cervélo',
            'Shimano', 'SRAM', 'Campagnolo', 'Continental', 'Vittoria', 'Schwalbe',
            // Camping & Outdoor
            'Guepardo', 'NTK', 'Nautika', 'Azteq', 'Coleman', 'Deuter',
            'Osprey', 'Gregory', 'Sea to Summit', 'Victorinox', 'Leatherman', 'Gerber',
        ],
    ];

    /**
     * Padrões regex para extração
     */
    private array $patterns = [
        // Memória RAM - padrão mais específico, deve ser verificado PRIMEIRO
        'ram' => [
            'pattern' => '/\b(\d+)\s*(gb|mb)\s*(ram|de\s*ram|memória\s*ram)\b/i',
            'attributes' => ['RAM', 'RAM_MEMORY'],
            'normalize' => true,
            'priority' => 1,
        ],
        // Capacidade de armazenamento (SSD, HD, etc)
        'storage' => [
            'pattern' => '/\b(\d+)\s*(gb|tb|mb|gigabytes?|terabytes?|megabytes?)\s*(ssd|hd|nvme|interno|interna|armazenamento)?\b/i',
            'attributes' => ['STORAGE_CAPACITY', 'INTERNAL_MEMORY'],
            'normalize' => true,
            'priority' => 2,
        ],
        // Capacidade genérica (quando não é RAM e não especifica tipo)
        'capacity_generic' => [
            'pattern' => '/\b(\d+)\s*(gb|tb)\b(?!\s*(ram|de\s*ram))/i',
            'attributes' => ['STORAGE_CAPACITY', 'INTERNAL_MEMORY', 'CAPACITY'],
            'normalize' => true,
            'priority' => 3,
        ],
        // Tamanho de tela
        'screen_size' => [
            'pattern' => '/\b(\d+(?:[.,]\d+)?)\s*(?:"|polegadas?|pol|inch(?:es)?)\b/i',
            'attributes' => ['SCREEN_SIZE', 'DISPLAY_SIZE', 'SIZE'],
            'normalize' => true,
            'priority' => 10,
        ],
        // Resolução
        'resolution' => [
            'pattern' => '/\b(full\s*hd|4k|8k|hd|fhd|uhd|qhd|1080p|720p|2160p|1440p|4320p)\b/i',
            'attributes' => ['RESOLUTION', 'DISPLAY_RESOLUTION'],
            'normalize' => true,
            'priority' => 10,
        ],
        // Voltagem
        'voltage' => [
            'pattern' => '/\b(110v?|220v?|bivolt|bi-volt|127v?)\b/i',
            'attributes' => ['VOLTAGE', 'LINE_VOLTAGE'],
            'normalize' => true,
            'priority' => 10,
        ],
        // Cores
        'color' => [
            'pattern' => '/\b(preto|branco|azul|vermelho|verde|amarelo|rosa|roxo|cinza|prata|dourado|gold|silver|black|white|blue|red|green|yellow|pink|purple|gray|grey|grafite|champagne|midnight|starlight)\b/i',
            'attributes' => ['COLOR', 'MAIN_COLOR', 'PRIMARY_COLOR'],
            'normalize' => true,
        ],
        // Processador
        'processor' => [
            'pattern' => '/\b(i[3579]|ryzen\s*[3579]|core\s*i[3579]|snapdragon\s*\d+|exynos\s*\d+|dimensity\s*\d+|helio\s*[a-z]\d+|a\d{2}\s*bionic|m[123]\s*(?:pro|max)?)\b/i',
            'attributes' => ['PROCESSOR', 'CPU', 'CHIPSET'],
            'normalize' => false,
        ],
        // Potência
        'power' => [
            'pattern' => '/\b(\d+(?:[.,]\d+)?)\s*(w|watts?|va|hp|cv)\b/i',
            'attributes' => ['POWER', 'POWER_CONSUMPTION', 'WATTAGE'],
            'normalize' => true,
        ],
        // Capacidade em litros
        'capacity_liters' => [
            'pattern' => '/\b(\d+(?:[.,]\d+)?)\s*(l|lt|litros?|liters?)\b/i',
            'attributes' => ['CAPACITY', 'VOLUME', 'CAPACITY_LITERS'],
            'normalize' => true,
        ],
        // Peso
        'weight' => [
            'pattern' => '/\b(\d+(?:[.,]\d+)?)\s*(kg|g|gramas?|kilos?|quilos?)\b/i',
            'attributes' => ['WEIGHT', 'PRODUCT_WEIGHT', 'NET_WEIGHT'],
            'normalize' => true,
        ],
        // Dimensões
        'dimensions' => [
            'pattern' => '/\b(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)\s*(?:x\s*(\d+(?:[.,]\d+)?))?\s*(cm|m|mm|metros?|centímetros?|milímetros?)?\b/i',
            'attributes' => ['DIMENSIONS', 'SIZE'],
            'normalize' => false,
        ],
        // Modelo/Versão
        'model' => [
            'pattern' => '/\b(v\d+|versão\s*\d+|version\s*\d+|gen\s*\d+|geração\s*\d+|generation\s*\d+|series?\s*\d+|série\s*\d+)\b/i',
            'attributes' => ['MODEL', 'VERSION', 'GENERATION'],
            'normalize' => false,
        ],
        // Ano
        'year' => [
            'pattern' => '/\b(20[12]\d)\b/',
            'attributes' => ['YEAR', 'MODEL_YEAR', 'RELEASE_YEAR'],
            'normalize' => false,
        ],
        // ========================================
        // PADRÕES ESPECÍFICOS PARA MOTOS/AUTOMOTIVO
        // ========================================
        // Tamanho de capacete (números como 56, 58, 60, etc)
        'helmet_size' => [
            'pattern' => '/\b(5[4-9]|6[0-4])\s*(?:cm)?\b/i',
            'attributes' => ['SIZE', 'HELMET_SIZE', 'HEAD_SIZE'],
            'normalize' => false,
            'priority' => 5,
        ],
        // Modelos de moto comuns - Honda, Yamaha, Suzuki, BMW, etc
        'moto_model' => [
            'pattern' => '/\b(cg\s*125|cg\s*150|cg\s*160|titan\s*125|titan\s*150|titan\s*160|fan\s*125|fan\s*150|fan\s*160|start\s*160|biz\s*100|biz\s*110|biz\s*125|bros\s*125|bros\s*150|bros\s*160|pop\s*100|pop\s*110|xre\s*190|xre\s*300|cb\s*300|cb\s*500|cb\s*650|cbx\s*250|pcx\s*150|ybr\s*125|ybr\s*factor|fazer\s*150|fazer\s*250|crosser\s*150|lander\s*250|mt[\s-]*03|mt[\s-]*07|mt[\s-]*09|ninja\s*250|ninja\s*300|ninja\s*400|z[\s-]*300|z[\s-]*400|duke\s*200|duke\s*390|gs\s*650|gs\s*800|gs\s*1200|f\s*800|f\s*850|r\s*1200|r\s*1250|s\s*1000|g\s*310|twister|hornet|cb\s*1000|cb\s*600|tenere|factor\s*125|factor\s*150|nmax|pcx|lead|sh\s*150|sh\s*300|africa\s*twin|transalp|varadero|burgman|intruder|v-strom|vstrom|boulevard|bandit|gsx|hayabusa|street\s*triple|tiger|speed\s*triple|bonneville|scrambler|monster|multistrada|panigale|diavel)\b/i',
            'attributes' => ['COMPATIBLE_VEHICLE_MODELS', 'VEHICLE_MODEL', 'MOTO_MODEL'],
            'normalize' => false,
            'priority' => 6,
        ],
        // Marcas de moto - expandido
        'moto_brand' => [
            'pattern' => '/\b(honda|yamaha|suzuki|kawasaki|dafra|shineray|haojue|kasinski|bmw|triumph|ducati|harley|harley[\s-]*davidson|ktm|royal\s*enfield|indian|aprilia|mv\s*agusta|benelli|moto\s*guzzi|husqvarna)\b/i',
            'attributes' => ['COMPATIBLE_VEHICLE_BRANDS', 'VEHICLE_BRAND'],
            'normalize' => true,
            'priority' => 6,
        ],
        // Cor fosca/brilho
        'finish' => [
            'pattern' => '/\b(fosco|fosca|brilhante|brilho|acetinado|acetinada|mate|matte|gloss|glossy)\b/i',
            'attributes' => ['FINISH', 'SURFACE_FINISH', 'COLOR_TYPE'],
            'normalize' => true,
            'priority' => 8,
        ],
        // Universal/Compatibilidade
        'compatibility' => [
            'pattern' => '/\b(universal|univ\.?|todas\s*as\s*motos?|compatível)\b/i',
            'attributes' => ['COMPATIBILITY', 'FIT_TYPE', 'APPLICATION'],
            'normalize' => true,
            'priority' => 9,
        ],
        // Material - expandido com materiais comuns em peças de moto/auto
        'material' => [
            'pattern' => '/\b(aço\s*inox(?:idável)?|inox|alumínio|plástico|madeira|mdf|vidro|couro|tecido|algodão|poliéster|nylon|silicone|borracha|metal|ferro|cobre|latão|abs|pvc|acrílico|cerâmica|porcelana|cromado|cromada|cromo|aço\s*carbono|carbono|fibra\s*de\s*carbono|polipropileno|pp|polietileno|pe|eva|espuma|poliuretano|pu|náilon|poliacetal|delrin|ptfe|teflon|vinil|policarbonato|pc)\b/i',
            'attributes' => ['MATERIAL', 'MAIN_MATERIAL', 'BODY_MATERIAL'],
            'normalize' => true,
        ],
        // Conectividade
        'connectivity' => [
            'pattern' => '/\b(wifi|wi-fi|bluetooth|bt\s*\d+\.\d+|usb(?:-c)?|type-c|hdmi|nfc|5g|4g|lte|3g)\b/i',
            'attributes' => ['CONNECTIVITY', 'WIRELESS', 'CONNECTION_TYPE'],
            'normalize' => true,
        ],
        // Garantia
        'warranty' => [
            'pattern' => '/\b(\d+)\s*(?:anos?|meses?|years?|months?)\s*(?:de\s*)?garantia\b/i',
            'attributes' => ['WARRANTY', 'WARRANTY_TIME', 'WARRANTY_PERIOD'],
            'normalize' => true,
        ],
    ];

    /**
     * Mapa de normalização de valores
     */
    private array $normalizations = [
        // Cores
        'black' => 'Preto',
        'white' => 'Branco',
        'blue' => 'Azul',
        'red' => 'Vermelho',
        'green' => 'Verde',
        'yellow' => 'Amarelo',
        'pink' => 'Rosa',
        'purple' => 'Roxo',
        'gray' => 'Cinza',
        'grey' => 'Cinza',
        'silver' => 'Prata',
        'gold' => 'Dourado',
        'midnight' => 'Azul Meia-Noite',
        'starlight' => 'Estelar',
        'grafite' => 'Grafite',
        'champagne' => 'Champagne',
        
        // Resolução
        'full hd' => 'Full HD',
        'fullhd' => 'Full HD',
        'fhd' => 'Full HD',
        '1080p' => 'Full HD',
        'hd' => 'HD',
        '720p' => 'HD',
        '4k' => '4K',
        '2160p' => '4K',
        'uhd' => '4K UHD',
        '8k' => '8K',
        '4320p' => '8K',
        'qhd' => 'QHD',
        '1440p' => 'QHD',
        
        // Voltagem
        '110v' => '110V',
        '110' => '110V',
        '127v' => '127V',
        '127' => '127V',
        '220v' => '220V',
        '220' => '220V',
        'bivolt' => 'Bivolt',
        'bi-volt' => 'Bivolt',
        
        // Conectividade
        'wifi' => 'Wi-Fi',
        'wi-fi' => 'Wi-Fi',
        'bluetooth' => 'Bluetooth',
        'usb-c' => 'USB-C',
        'type-c' => 'USB-C',
        'usb' => 'USB',
        'hdmi' => 'HDMI',
        'nfc' => 'NFC',
        '5g' => '5G',
        '4g' => '4G',
        'lte' => '4G LTE',
        '3g' => '3G',
        
        // Materiais
        'aço inox' => 'Aço Inoxidável',
        'aço inoxidável' => 'Aço Inoxidável',
        'inox' => 'Aço Inoxidável',
        'alumínio' => 'Alumínio',
        'plástico' => 'Plástico',
        'madeira' => 'Madeira',
        'mdf' => 'MDF',
        'vidro' => 'Vidro',
        'couro' => 'Couro',
        'tecido' => 'Tecido',
        'algodão' => 'Algodão',
        'poliéster' => 'Poliéster',
        'nylon' => 'Nylon',
        'náilon' => 'Nylon',
        'silicone' => 'Silicone',
        'borracha' => 'Borracha',
        'metal' => 'Metal',
        'abs' => 'Plástico ABS',
        'pvc' => 'PVC',
        'acrílico' => 'Acrílico',
        'cerâmica' => 'Cerâmica',
        'porcelana' => 'Porcelana',
        'cromado' => 'Cromado',
        'cromada' => 'Cromado',
        'cromo' => 'Cromado',
        'aço carbono' => 'Aço Carbono',
        'carbono' => 'Carbono',
        'fibra de carbono' => 'Fibra de Carbono',
        'polipropileno' => 'Polipropileno',
        'pp' => 'Polipropileno',
        'polietileno' => 'Polietileno',
        'pe' => 'Polietileno',
        'eva' => 'EVA',
        'espuma' => 'Espuma',
        'poliuretano' => 'Poliuretano',
        'pu' => 'Poliuretano',
        'poliacetal' => 'Poliacetal',
        'delrin' => 'Poliacetal',
        'ptfe' => 'PTFE',
        'teflon' => 'PTFE',
        'vinil' => 'Vinil',
        'policarbonato' => 'Policarbonato',
        'pc' => 'Policarbonato',
        
        // Acabamento
        'fosco' => 'Fosco',
        'fosca' => 'Fosco',
        'brilhante' => 'Brilhante',
        'brilho' => 'Brilhante',
        'acetinado' => 'Acetinado',
        'acetinada' => 'Acetinado',
        'mate' => 'Fosco',
        'matte' => 'Fosco',
        'gloss' => 'Brilhante',
        'glossy' => 'Brilhante',
        'texturizado' => 'Texturizado',
        'texturizada' => 'Texturizado',
        
        // Marcas de moto
        'honda' => 'Honda',
        'yamaha' => 'Yamaha',
        'suzuki' => 'Suzuki',
        'kawasaki' => 'Kawasaki',
        'dafra' => 'Dafra',
        'shineray' => 'Shineray',
        'haojue' => 'Haojue',
        'kasinski' => 'Kasinski',
        'bmw motorrad' => 'BMW Motorrad',
        'triumph' => 'Triumph',
        'ducati' => 'Ducati',
        'harley' => 'Harley-Davidson',
        'ktm' => 'KTM',
        
        // Compatibilidade
        'universal' => 'Universal',
        'univ.' => 'Universal',
        'univ' => 'Universal',
    ];

    /**
     * Extrai todos os atributos possíveis do título
     * 
     * @param string $title Título do produto
     * @param array $allowedAttributes Lista de IDs de atributos permitidos (opcional)
     * @param string|null $categoryType Tipo de categoria para busca de marca
     * @return array Lista de atributos extraídos com confiança
     */
    public function extractFromTitle(string $title, array $allowedAttributes = [], ?string $categoryType = null): array
    {
        $extracted = [];
        $titleLower = mb_strtolower($title);
        
        // 1. Extrair marca
        $brand = $this->extractBrand($title, $categoryType);
        if ($brand) {
            $extracted[] = [
                'attribute_id' => 'BRAND',
                'value' => $brand['value'],
                'confidence' => $brand['confidence'],
                'source' => 'TITLE',
                'method' => 'dictionary_match',
            ];
        }
        
        // 2. Ordenar padrões por prioridade
        $sortedPatterns = $this->patterns;
        uasort($sortedPatterns, function($a, $b) {
            return ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99);
        });
        
        // 3. Aplicar padrões regex (ordenados por prioridade)
        $processedValues = []; // Evitar extrair o mesmo valor duas vezes
        
        // Padrões que podem ter múltiplos matches (ex: várias marcas/modelos)
        $multiMatchPatterns = ['storage', 'capacity_generic', 'moto_model', 'moto_brand'];
        
        foreach ($sortedPatterns as $patternName => $config) {
            // Para alguns padrões, extrair todos os matches
            if (in_array($patternName, $multiMatchPatterns)) {
                preg_match_all($config['pattern'], $title, $allMatches, PREG_SET_ORDER);
                foreach ($allMatches as $matches) {
                    $value = $this->processMatch($patternName, $matches, $config);
                    // Usar chave normalizada para detectar duplicatas (attrId + valor_lower)
                    $attrId = $this->findMatchingAttribute($config['attributes'], $allowedAttributes);
                    $uniqueKey = $attrId . ':' . mb_strtolower($value ?? '');
                    
                    if ($value !== null && !isset($processedValues[$uniqueKey]) && $attrId) {
                        $extracted[] = [
                            'attribute_id' => $attrId,
                            'value' => $value,
                            'confidence' => $this->calculateConfidence($patternName, $matches),
                            'source' => 'TITLE',
                            'method' => 'regex_extraction',
                        ];
                        $processedValues[$uniqueKey] = true;
                    }
                }
            } else {
                // Para outros padrões, pegar apenas o primeiro match
                $matches = [];
                if (preg_match($config['pattern'], $title, $matches)) {
                    $value = $this->processMatch($patternName, $matches, $config);
                    
                    if ($value !== null && !isset($processedValues[$value])) {
                        // Verificar qual atributo usar baseado nos permitidos
                        $attrId = $this->findMatchingAttribute($config['attributes'], $allowedAttributes);
                        
                        if ($attrId) {
                            $extracted[] = [
                                'attribute_id' => $attrId,
                                'value' => $value,
                                'confidence' => $this->calculateConfidence($patternName, $matches),
                                'source' => 'TITLE',
                                'method' => 'regex_extraction',
                            ];
                            $processedValues[$value] = true;
                        }
                    }
                }
            }
        }
        
        // 4. Remover duplicatas (manter maior confiança)
        $extracted = $this->deduplicateByAttribute($extracted);
        
        return $extracted;
    }

    /**
     * Extrai marca do título
     */
    private function extractBrand(string $title, ?string $categoryType = null): ?array
    {
        $titleLower = mb_strtolower($title);
        
        // Determinar dicionários a usar
        $dictionaries = ['electronics']; // Default
        if ($categoryType && isset($this->brandDictionary[$categoryType])) {
            $dictionaries = [$categoryType];
        } else {
            // Usar todos os dicionários
            $dictionaries = array_keys($this->brandDictionary);
        }
        
        foreach ($dictionaries as $dict) {
            foreach ($this->brandDictionary[$dict] ?? [] as $brand) {
                $brandLower = mb_strtolower($brand);
                
                // Match exato com word boundary
                $pattern = '/\b' . preg_quote($brandLower, '/') . '\b/i';
                if (preg_match($pattern, $title)) {
                    // Verificar posição no título (início = maior confiança)
                    $position = mb_stripos($titleLower, $brandLower);
                    $confidence = 90;
                    
                    if ($position !== false && $position < 20) {
                        $confidence = 95;
                    }
                    
                    return [
                        'value' => $brand, // Usar capitalização correta
                        'confidence' => $confidence,
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Processa o resultado do match regex
     */
    private function processMatch(string $patternName, array $matches, array $config): ?string
    {
        $value = null;
        
        switch ($patternName) {
            case 'storage':
            case 'ram':
            case 'capacity_generic':
                $num = $matches[1];
                $unit = mb_strtoupper($matches[2]);
                // Normalizar unidades
                if (in_array($unit, ['GIGABYTES', 'GIGABYTE', 'GB'])) {
                    $unit = 'GB';
                } elseif (in_array($unit, ['TERABYTES', 'TERABYTE', 'TB'])) {
                    $unit = 'TB';
                } elseif (in_array($unit, ['MEGABYTES', 'MEGABYTE', 'MB'])) {
                    $unit = 'MB';
                }
                $value = "{$num} {$unit}";
                break;
                
            case 'screen_size':
                $num = str_replace(',', '.', $matches[1]);
                $value = "{$num}\"";
                break;
                
            case 'power':
                $num = str_replace(',', '.', $matches[1]);
                $unit = mb_strtoupper($matches[2]);
                if (in_array($unit, ['WATTS', 'WATT'])) {
                    $unit = 'W';
                }
                $value = "{$num}{$unit}";
                break;
                
            case 'capacity_liters':
                $num = str_replace(',', '.', $matches[1]);
                $value = "{$num}L";
                break;
                
            case 'weight':
                $num = str_replace(',', '.', $matches[1]);
                $unit = mb_strtolower($matches[2]);
                if (in_array($unit, ['gramas', 'grama', 'g'])) {
                    $unit = 'g';
                } elseif (in_array($unit, ['kilos', 'kilo', 'quilos', 'quilo', 'kg'])) {
                    $unit = 'kg';
                }
                $value = "{$num}{$unit}";
                break;
                
            case 'warranty':
                $num = $matches[1];
                $unit = mb_strtolower($matches[2] ?? '');
                if (str_contains($unit, 'ano') || str_contains($unit, 'year')) {
                    $value = "{$num} anos";
                } else {
                    $value = "{$num} meses";
                }
                break;
                
            case 'year':
                $value = $matches[1];
                break;
                
            default:
                $value = trim($matches[0]);
                break;
        }
        
        // Aplicar normalização se configurado
        if ($value && ($config['normalize'] ?? false)) {
            $value = $this->normalizeValue($value);
        }
        
        return $value;
    }

    /**
     * Normaliza valor extraído
     */
    private function normalizeValue(string $value): string
    {
        $valueLower = mb_strtolower(trim($value));
        
        // Verificar se já está no dicionário de normalização
        if (isset($this->normalizations[$valueLower])) {
            return $this->normalizations[$valueLower];
        }
        
        // Normalização parcial
        foreach ($this->normalizations as $key => $normalized) {
            if (str_contains($valueLower, $key)) {
                return str_ireplace($key, $normalized, $value);
            }
        }
        
        // Padrões de capacidade: manter unidade em maiúsculas
        if (preg_match('/^(\d+)\s*(GB|TB|MB|KB)$/i', $value, $matches)) {
            return $matches[1] . ' ' . mb_strtoupper($matches[2]);
        }
        
        // Capitalizar primeira letra como fallback (mas preservar siglas)
        if (preg_match('/^[A-Z0-9\s\-]+$/', $value)) {
            return $value; // Já está em maiúsculas/formatado
        }
        
        return mb_convert_case($value, MB_CASE_TITLE);
    }

    /**
     * Encontra o atributo correspondente na lista permitida
     */
    private function findMatchingAttribute(array $possibleIds, array $allowedAttributes): ?string
    {
        if (empty($allowedAttributes)) {
            // Retorna o primeiro se não houver restrição
            return $possibleIds[0] ?? null;
        }
        
        // Criar mapa de IDs permitidos
        $allowedMap = [];
        foreach ($allowedAttributes as $attr) {
            $id = is_array($attr) ? ($attr['id'] ?? null) : $attr;
            if ($id) {
                $allowedMap[$id] = true;
            }
        }
        
        // Encontrar correspondência
        foreach ($possibleIds as $id) {
            if (isset($allowedMap[$id])) {
                return $id;
            }
        }
        
        return null;
    }

    /**
     * Calcula confiança baseada no tipo de extração
     */
    private function calculateConfidence(string $patternName, array $matches): int
    {
        $base = 85;
        
        // Padrões mais específicos têm maior confiança
        $specificity = [
            'resolution' => 92,
            'voltage' => 90,
            'color' => 88,
            'storage' => 88,
            'ram' => 88,
            'screen_size' => 85,
            'processor' => 85,
            'year' => 90,
            'connectivity' => 85,
            'material' => 80,
            'power' => 82,
            'capacity_liters' => 82,
            'weight' => 80,
            'warranty' => 88,
            'model' => 75,
            'dimensions' => 70,
        ];
        
        return $specificity[$patternName] ?? $base;
    }

    /**
     * Remove duplicatas mantendo maior confiança
     * Para atributos que suportam múltiplos valores, agrupa os valores
     */
    private function deduplicateByAttribute(array $extracted): array
    {
        // Atributos que podem ter múltiplos valores
        $multiValueAttributes = [
            'COMPATIBLE_VEHICLE_MODELS',
            'COMPATIBLE_VEHICLE_BRANDS',
            'VEHICLE_MODEL',
            'VEHICLE_BRAND',
            'MOTO_MODEL',
        ];
        
        $byAttr = [];
        $multiValues = []; // Para atributos de múltiplos valores
        
        foreach ($extracted as $item) {
            $attrId = $item['attribute_id'];
            
            if (in_array($attrId, $multiValueAttributes)) {
                // Para multi-valores, guardar todos os valores únicos
                $key = $attrId . ':' . mb_strtolower($item['value']);
                if (!isset($multiValues[$key])) {
                    $multiValues[$key] = $item;
                }
            } elseif (!isset($byAttr[$attrId]) || $item['confidence'] > $byAttr[$attrId]['confidence']) {
                $byAttr[$attrId] = $item;
            }
        }
        
        return array_merge(array_values($byAttr), array_values($multiValues));
    }

    /**
     * Detecta o tipo de categoria baseado no título
     */
    public function detectCategoryType(string $title): ?string
    {
        $titleLower = mb_strtolower($title);
        
        $indicators = [
            'electronics' => ['celular', 'smartphone', 'tablet', 'notebook', 'laptop', 'tv', 'monitor', 'fone', 'headphone', 'mouse', 'teclado', 'webcam', 'câmera', 'console', 'videogame'],
            'appliances' => ['geladeira', 'refrigerador', 'fogão', 'microondas', 'liquidificador', 'batedeira', 'cafeteira', 'torradeira', 'aspirador', 'ventilador', 'ar condicionado', 'lava', 'seca', 'ferro de passar'],
            'fashion' => ['camiseta', 'camisa', 'calça', 'vestido', 'saia', 'blusa', 'tênis', 'sapato', 'sandália', 'bota', 'bolsa', 'mochila', 'relógio', 'óculos'],
            'computers' => ['processador', 'cpu', 'placa de vídeo', 'gpu', 'memória ram', 'ssd', 'hd', 'fonte', 'gabinete', 'cooler', 'placa mãe', 'motherboard'],
            'automotive' => ['pneu', 'óleo', 'filtro', 'bateria', 'pastilha', 'amortecedor', 'escapamento', 'vela', 'bobina', 'radiador'],
        ];
        
        foreach ($indicators as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($titleLower, $keyword)) {
                    return $type;
                }
            }
        }
        
        return null;
    }

    /**
     * Adiciona marcas customizadas ao dicionário
     */
    public function addBrands(string $category, array $brands): void
    {
        if (!isset($this->brandDictionary[$category])) {
            $this->brandDictionary[$category] = [];
        }
        
        $this->brandDictionary[$category] = array_merge(
            $this->brandDictionary[$category],
            $brands
        );
    }

    /**
     * Adiciona padrão customizado
     */
    public function addPattern(string $name, string $pattern, array $attributes, bool $normalize = false): void
    {
        $this->patterns[$name] = [
            'pattern' => $pattern,
            'attributes' => $attributes,
            'normalize' => $normalize,
        ];
    }

    /**
     * Adiciona normalização customizada
     */
    public function addNormalization(string $from, string $to): void
    {
        $this->normalizations[mb_strtolower($from)] = $to;
    }
}
