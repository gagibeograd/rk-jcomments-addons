/**
 * RK add-ons for JComments: emoji picker for the "rk" template.
 * Hooks into the smilies button (a.bbcode-smile) the JComments core renders and, instead of the old smilie images,
 * opens a Unicode emoji picker: categories, search (English keywords from the Unicode names plus the keywords in the
 * data, diacritics folded), "Recent". The emoji is inserted as text at the cursor in the textarea. No core changes.
 * Texts come from window.rkjcI18n (set by tpl_form.php from the files_rkjcomments language file); English fallback.
 */
(function () {
	'use strict';

	var RECENT_KEY = 'rkjc_emoji_recent', RECENT_MAX = 24;
	var I18N = window.rkjcI18n || {};
	var T = {
		title: I18N.title || 'Emoji',
		search: I18N.search || 'Search (e.g. heart, coffee, pizza)',
		searchLabel: I18N.searchLabel || 'Search emoji',
		recent: I18N.recent || 'Recent',
		results: I18N.results || 'Results (%d)',
		noResults: I18N.noResults || 'No results',
		close: I18N.close || 'Close',
		cat: {
			smileys: 'Smileys', people: 'Gestures and people', nature: 'Animals and nature', food: 'Food and drink',
			activities: 'Activities', travel: 'Travel and places', objects: 'Objects', symbols: 'Symbols'
		}
	};
	Object.keys(I18N.cat || {}).forEach(function (k) { if (I18N.cat[k]) { T.cat[k] = I18N.cat[k]; } });
	T.cat.recent = T.recent;
	// Tooltip language: the second keyword group (after the English one) when the page language starts with "sr"
	var SR = /^sr/i.test(document.documentElement.lang || '');

	function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); }

	// Categories: [key, tab icon, [ "emoji|english keywords|extra keywords", ... ]]
	var DATA = [
		['smileys', '😀', [
			'😀|grinning|osmeh srecan', '😃|smiling open mouth|osmeh', '😄|smiling open mouth eyes|osmeh oci', '😁|grinning smiling eyes|kez zubi', '😆|smiling open mouth tightly closed eyes|smeh', '😅|smiling open mouth cold sweat|smeh znoj', '🤣|rolling on floor laughing|smeh pod', '😂|tears joy|smeh suze',
			'🙂|slightly smiling|osmeh blagi', '🙃|upside down|naopako', '😉|winking|namig', '😊|smiling eyes|rumen srecan', '😇|smiling halo|andjeo oreol', '🥰|smiling eyes three hearts|zaljubljen srca', '😍|smiling heart shaped eyes|zaljubljen oci srca',
			'🤩|grinning star eyes|zvezde ocaran', '😘|throwing kiss|poljubac srce', '😗|kissing|poljubac', '😚|kissing closed eyes|poljubac zatvorene oci', '😙|kissing smiling eyes|poljubac osmeh', '🥲|smiling tear|suza osmeh',
			'😋|savouring delicious food|ukusno njam', '😛|stuck out tongue|jezik', '😜|stuck out tongue winking eye|jezik namig', '🤪|grinning one large small eye|lud', '😝|stuck out tongue tightly closed eyes|jezik zatvorene oci', '🤑|money mouth|novac', '🤗|hugging|zagrljaj', '🤭|smiling eyes hand covering mouth|ruka usta',
			'🤫|finger covering closed lips|tiho psst', '🤔|thinking|razmisljanje', '🤐|zipper mouth|zatvorena usta', '🤨|one eyebrow raised|sumnja obrva', '😐|neutral|neutralan', '😑|expressionless|bezizrazan', '😶|without mouth|bez usta',
			'😏|smirking|prepreden', '😒|unamused|nezadovoljan', '🙄|rolling eyes|kolutanje ocima', '😬|grimacing|grimasa', '🤥|lying|laz nos', '😌|relieved|olaksanje', '😔|pensive|tuzan zamisljen',
			'😪|sleepy|pospan', '🤤|drooling|balavljenje', '😴|sleeping|spavanje', '😷|medical mask|maska bolestan', '🤒|thermometer|temperatura', '🤕|head bandage|povreda zavoj', '🤢|nauseated|muka',
			'🤮|open mouth vomiting|povracanje', '🤧|sneezing|kijanje', '🥵|overheated|vrucina', '🥶|freezing|hladno', '🥴|uneven eyes wavy mouth|omamljen', '😵|dizzy|vrtoglavica', '🤯|shocked exploding head|eksplozija glava',
			'🤠|cowboy hat|kauboj', '🥳|party horn hat|zurka', '🥸|disguised|prerusen', '😎|smiling sunglasses|naocare kul', '🤓|nerd|strebe naocare', '🧐|monocle|monokl', '😕|confused|zbunjen', '😟|worried|zabrinut',
			'🙁|slightly frowning|tuzan', '☹️|white frowning|tuzan', '😮|open mouth|iznenadjen', '😯|hushed|zapanjen', '😲|astonished|sokiran', '😳|flushed|crven stid', '🥺|pleading eyes|molim oci', '😦|frowning open mouth|uplasen',
			'😧|anguished|bol', '😨|fearful|strah', '😰|open mouth cold sweat|strah znoj', '😥|disappointed but relieved|razocaran', '😢|crying|plac', '😭|loudly crying|plac glasno', '😱|screaming in fear|vrisak', '😖|confounded|zbunjen', '😣|persevering|uporan',
			'😞|disappointed|razocaran', '😓|cold sweat|znoj', '😩|weary|umoran', '😫|tired|iscrpljen', '🥱|yawning|zevanje', '😤|look triumph|besan nos', '😡|pouting|besan crven', '😠|angry|ljut',
			'🤬|serious symbols covering mouth|psovke', '😈|smiling horns|djavo', '👿|imp|djavo ljut', '💀|skull|lobanja', '☠️|skull crossbones|lobanja kosti', '💩|pile poo|kaka', '🤡|clown|klovn', '👹|japanese ogre|ogr', '👺|japanese goblin|goblin',
			'👻|ghost|duh', '👽|extraterrestrial alien|vanzemaljac', '🤖|robot|robot', '😺|smiling cat open mouth|macka osmeh', '😸|grinning cat smiling eyes|macka kez', '😹|cat tears joy|macka smeh', '😻|smiling cat heart shaped eyes|macka srca', '😽|kissing cat closed eyes|macka poljubac',
			'🙈|see no evil monkey|majmun ne vidi', '🙉|hear no evil monkey|majmun ne cuje', '🙊|speak no evil monkey|majmun ne govori'
		]],
		['people', '👍', [
			'👋|waving hand|mahanje zdravo', '🤚|raised back hand|dlan', '🖐️|raised hand fingers splayed|saka prsti', '✋|raised hand|dlan stop', '🖖|raised hand part between middle ring fingers|vulkan', '👌|ok hand|ok', '🤌|pinched fingers|prsti italijan', '🤏|pinching hand|malo',
			'✌️|victory hand|pobeda mir', '🤞|hand index middle fingers crossed|prekrsteni prsti sreca', '🤟|i love you hand|volim te', '🤘|horns|rok', '🤙|call me hand|pozovi', '👈|white left pointing backhand index|levo', '👉|white right pointing backhand index|desno', '👆|white up pointing backhand index|gore',
			'🖕|reversed hand middle finger extended|srednji prst', '👇|white down pointing backhand index|dole', '☝️|white up pointing index|kaziprst', '👍|thumbs up|palac gore super', '👎|thumbs down|palac dole', '✊|raised fist|pesnica', '👊|fisted hand|udarac', '🤛|left facing fist|pesnica levo',
			'🤜|right facing fist|pesnica desno', '👏|clapping hands|aplauz', '🙌|person raising both hands in celebration|slavlje ruke', '👐|open hands|otvorene ruke', '🤲|palms up together|dlanovi', '🤝|handshake|rukovanje', '🙏|person folded hands|molitva hvala',
			'✍️|writing hand|pisanje', '💅|nail polish|nokti', '🤳|selfie|selfi', '💪|flexed biceps|misic snaga', '🦵|leg|noga', '🦶|foot|stopalo', '👂|ear|uvo', '👃|nose|nos', '🧠|brain|mozak', '🫀|anatomical heart|srce organ',
			'👀|eyes|oci', '👁️|eye|oko', '👅|tongue|jezik', '👄|mouth|usta usne', '👶|baby|beba', '🧒|child|dete', '👦|boy|decak', '👧|girl|devojcica', '🧑|adult|osoba', '👱|person blond hair|plavokos',
			'👨|man|muskarac', '🧔|bearded person|brada', '👩|woman|zena', '🧓|older adult|stariji', '👴|older man|deda', '👵|older woman|baka', '🙍|person frowning|mrstenje', '🙎|person pouting|durenje', '🙅|no good gesture|ne', '🙆|ok gesture|ok ruke',
			'💁|information desk person|informacije', '🙋|happy person raising one hand|dizanje ruke', '🧏|deaf person|gluv', '🙇|person bowing deeply|naklon', '🤦|palm|facepalm', '🤷|shrug|sleganje ramenima', '👨‍⚕️|man staff aesculapius|doktor', '👩‍⚕️|woman staff aesculapius|doktorka',
			'👨‍🍳|man cooking|kuvar', '👩‍🍳|woman cooking|kuvarica', '👨‍🌾|man ear rice|farmer', '👩‍🌾|woman ear rice|farmerka', '👨‍🏫|man school|ucitelj', '👩‍🏫|woman school|uciteljica', '👮|police officer|policajac', '👷|construction worker|gradjevinac',
			'💂|guardsman|garda', '🕵️|sleuth or spy|detektiv', '🧑‍💻|adult personal computer|programer', '👰|bride veil|mlada', '🤵|man in tuxedo|mladozenja', '🤰|pregnant woman|trudnica', '🤱|breast feeding|dojenje', '👼|baby angel|andjeo', '🎅|father christmas|deda mraz',
			'🦸|superhero|superheroj', '🧙|mage|carobnjak', '🧚|fairy|vila', '🧛|vampire|vampir', '🧜|merperson|sirena', '🧟|zombie|zombi', '💃|dancer|ples zena', '🕺|man dancing|ples muskarac', '🧘|person in lotus position|joga',
			'👫|man woman holding hands|par', '👭|two women holding hands|drugarice', '👬|two men holding hands|drugari', '💏|kiss|poljubac par', '💑|couple heart|par srce', '👪|family|porodica', '👨‍👩‍👧|man woman girl|porodica', '👨‍👩‍👧‍👦|man woman girl boy|porodica'
		]],
		['nature', '🐶', [
			'🐶|dog|pas', '🐱|cat|macka', '🐭|mouse|mis', '🐹|hamster|hrcak', '🐰|rabbit|zec', '🦊|fox|lisica', '🐻|bear|medved', '🐼|panda|panda', '🐨|koala|koala', '🐯|tiger|tigar', '🦁|lion|lav',
			'🐮|cow|krava', '🐷|pig|svinja', '🐽|pig nose|njuska', '🐸|frog|zaba', '🐵|monkey|majmun', '🐔|chicken|kokoska', '🐧|penguin|pingvin', '🐦|bird|ptica', '🐤|baby chick|pile', '🦆|duck|patka',
			'🦅|eagle|orao', '🦉|owl|sova', '🦇|bat|slepi mis', '🐺|wolf|vuk', '🐗|boar|divlja svinja', '🐴|horse|konj', '🦄|unicorn|jednorog', '🐝|honeybee|pcela', '🐛|bug|gusenica',
			'🦋|butterfly|leptir', '🐌|snail|puz', '🐞|lady beetle|bubamara', '🐜|ant|mrav', '🦟|mosquito|komarac', '🐢|turtle|kornjaca', '🐍|snake|zmija', '🦎|lizard|guster', '🐙|octopus|hobotnica', '🦑|squid|lignja',
			'🦐|shrimp|skamp', '🦀|crab|rak', '🐡|blowfish|riba', '🐠|tropical fish|riba', '🐟|fish|riba', '🐬|dolphin|delfin', '🐳|spouting whale|kit', '🦈|shark|ajkula', '🐊|crocodile|krokodil', '🐘|elephant|slon', '🦒|giraffe|zirafa',
			'🐪|dromedary camel|kamila', '🦙|llama|lama', '🐐|goat|koza', '🐑|sheep|ovca', '🐏|ram|ovan', '🐓|rooster|petao', '🦃|turkey|curka', '🦚|peacock|paun', '🦜|parrot|papagaj', '🐇|rabbit|zec', '🦔|hedgehog|jez',
			'🐾|paw prints|sape', '🌵|cactus|kaktus', '🎄|christmas tree|jelka', '🌲|evergreen tree|cetinar', '🌳|deciduous tree|drvo', '🌴|palm tree|palma', '🌱|seedling|klica', '🌿|herb|biljka list', '☘️|shamrock|detelina', '🍀|four leaf clover|detelina sreca',
			'🍁|maple leaf|javor jesen', '🍂|fallen leaf|lisce jesen', '🍃|leaf fluttering in wind|list vetar', '🌾|ear rice|zito psenica', '💐|bouquet|buket', '🌷|tulip|lala', '🌹|rose|ruza', '🥀|wilted flower|uvela ruza', '🌺|hibiscus|hibiskus',
			'🌸|cherry blossom|tresnja cvet', '🌼|blossom|cvet', '🌻|sunflower|suncokret', '🌞|sun|sunce lice', '🌝|full moon|mesec lice', '🌙|crescent moon|mesec', '⭐|white medium star|zvezda', '🌟|glowing star|zvezda sjaj', '✨|sparkles|iskre',
			'⚡|high voltage|munja', '🔥|fire|vatra', '🌈|rainbow|duga', '☀️|black sun rays|sunce', '⛅|sun behind cloud|oblak sunce', '☁️|cloud|oblak', '🌧️|cloud rain|kisa', '⛈️|thunder cloud rain|oluja', '❄️|snowflake|pahulja sneg', '☃️|snowman|sneško',
			'🌊|water wave|talas more', '💧|droplet|kap', '🌍|earth globe europe africa|zemlja planeta', '🌎|earth globe americas|zemlja', '🌏|earth globe asia australia|zemlja'
		]],
		['food', '🍔', [
			'🍏|green apple|zelena jabuka', '🍎|red apple|jabuka', '🍐|pear|kruska', '🍊|tangerine|pomorandza', '🍋|lemon|limun', '🍌|banana|banana', '🍉|watermelon|lubenica', '🍇|grapes|grozdje', '🍓|strawberry|jagoda',
			'🫐|blueberries|borovnica', '🍈|melon|dinja', '🍒|cherries|tresnja visnja', '🍑|peach|breskva', '🥭|mango|mango', '🍍|pineapple|ananas', '🥥|coconut|kokos', '🥝|kiwifruit|kivi', '🍅|tomato|paradajz',
			'🍆|aubergine|patlidzan', '🥑|avocado|avokado', '🥦|broccoli|brokoli', '🥬|leafy green|zelena salata kupus', '🥒|cucumber|krastavac', '🌶️|hot pepper|paprika ljuta', '🫑|bell pepper|paprika', '🌽|ear maize|kukuruz',
			'🥕|carrot|sargarepa', '🫒|olive|maslina', '🧄|garlic|beli luk', '🧅|onion|crni luk', '🥔|potato|krompir', '🍠|roasted sweet potato|batat', '🥐|croissant|kroasan', '🥯|bagel|djevrek', '🍞|bread|hleb',
			'🥖|baguette bread|baget', '🥨|pretzel|perec', '🧀|cheese wedge|sir', '🥚|egg|jaje', '🍳|cooking|jaje na oko tiganj', '🧈|butter|puter', '🥞|pancakes|palacinke', '🧇|waffle|vafle', '🥓|bacon|slanina',
			'🥩|cut meat|meso snicla', '🍗|poultry leg|batak', '🍖|meat on bone|meso kost', '🌭|hot dog|hot dog viršla', '🍔|hamburger|hamburger', '🍟|french fries|pomfrit', '🍕|slice pizza|pica', '🫓|flatbread|lepinja', '🥪|sandwich|sendvic',
			'🥙|stuffed flatbread|pita giros', '🧆|falafel|falafel', '🌮|taco|tako', '🌯|burrito|burito', '🥗|green salad|salata', '🥘|shallow pan food|paela tiganj', '🫕|fondue|fondi', '🍝|spaghetti|spagete pasta', '🍜|steaming bowl|supa nudle',
			'🍲|pot food|corba lonac', '🍛|curry rice|kari pirinac', '🍣|sushi|susi', '🍱|bento box|bento', '🥟|dumpling|knedla', '🦪|oyster|ostriga', '🍤|fried shrimp|skampi przeni', '🍙|rice ball|pirinac', '🍚|cooked rice|pirinac',
			'🍘|rice cracker|pirinac krekeri', '🍥|fish cake swirl design|riba kolac', '🥮|moon cake|mesecev kolac', '🍢|oden|raznjic', '🧁|cupcake|kapkejk', '🍰|shortcake|kolac torta parce', '🎂|birthday cake|torta rodjendan',
			'🍮|custard|puding', '🍭|lollipop|lilihip', '🍬|candy|bombona', '🍫|chocolate bar|cokolada', '🍿|popcorn|kokice', '🍩|doughnut|krofna', '🍪|cookie|keks', '🌰|chestnut|kesten', '🥜|peanuts|kikiriki',
			'🍯|honey pot|med', '🥛|glass milk|mleko', '🍼|baby bottle|flasica', '☕|hot beverage|kafa', '🫖|teapot|cajnik', '🍵|teacup without handle|caj', '🧃|beverage box|sok kutija', '🥤|cup straw|sok casa', '🧋|bubble tea|bubble tea', '🍶|sake bottle cup|sake',
			'🍺|beer mug|pivo', '🍻|clinking beer mugs|pivo zivjeli', '🥂|clinking glasses|sampanjac zivjeli', '🍷|wine glass|vino', '🥃|tumbler glass|viski rakija', '🍸|cocktail glass|koktel', '🍹|tropical drink|koktel tropski', '🧉|mate drink|mate',
			'🍾|bottle popping cork|sampanjac flasa', '🧊|ice cube|led', '🥄|spoon|kasika', '🍴|fork knife|viljuska noz', '🍽️|fork knife plate|tanjir pribor', '🥣|bowl spoon|cinija', '🥢|chopsticks|stapici', '🧂|salt shaker|so', '🫙|jar|tegla'
		]],
		['activities', '⚽', [
			'⚽|soccer ball|fudbal lopta', '🏀|basketball hoop|kosarka', '🏈|american football|americki fudbal', '⚾|baseball|bejzbol', '🎾|tennis racquet ball|tenis', '🏐|volleyball|odbojka', '🏉|rugby football|ragbi', '🎱|billiards|bilijar', '🏓|table tennis paddle ball|stoni tenis',
			'🏸|badminton racquet shuttlecock|badminton', '🥅|goal net|gol', '⛳|flag in hole|golf', '🏹|bow arrow|streljastvo luk', '🎣|fishing pole fish|pecanje', '🥊|boxing glove|boks', '🥋|martial arts uniform|karate', '⛸️|ice skate|klizanje', '🎿|ski boot|skijanje',
			'🛷|sled|sanke', '🏋️|weight lifter|tegovi', '🤸|person doing cartwheel|gimnastika', '🤼|wrestlers|rvanje', '🚴|bicyclist|biciklizam', '🏊|swimmer|plivanje', '🏄|surfer|surf', '🚣|rowboat|veslanje', '🧗|person climbing|penjanje',
			'🏆|trophy|pehar pobeda', '🥇|first place medal|zlato medalja', '🥈|second place medal|srebro', '🥉|third place medal|bronza', '🏅|sports medal|medalja', '🎖️|military medal|orden', '🎪|circus tent|cirkus', '🎭|performing arts|pozoriste maske',
			'🎨|artist palette|slikanje paleta', '🎬|clapper board|film klapa', '🎤|microphone|mikrofon pevanje', '🎧|headphone|slusalice', '🎼|musical score|note', '🎵|musical note|nota muzika', '🎶|multiple musical notes|note muzika',
			'🎹|musical keyboard|klavir', '🥁|drum drumsticks|bubnjevi', '🎷|saxophone|saksofon', '🎺|trumpet|truba', '🎸|guitar|gitara', '🪗|accordion|harmonika', '🎻|violin|violina', '🎲|game die|kocka igra', '♟️|black chess pawn|sah',
			'🎯|direct hit|pikado meta', '🎳|bowling|kuglanje', '🎮|video game|video igra', '🕹️|joystick|dzojstik', '🧩|jigsaw puzzle piece|slagalica', '🎰|slot machine|slot'
		]],
		['travel', '🚗', [
			'🚗|automobile|auto', '🚕|taxi|taksi', '🚙|recreational vehicle|dzip', '🚌|bus|autobus', '🚎|trolleybus|trolejbus', '🏎️|racing car|trkacki auto', '🚓|police car|policija auto', '🚑|ambulance|hitna pomoc', '🚒|fire engine|vatrogasci',
			'🚐|minibus|kombi', '🚚|delivery truck|kamion', '🚜|tractor|traktor', '🛵|motor scooter|skuter', '🏍️|racing motorcycle|motor', '🚲|bicycle|bicikl', '🛴|scooter|trotinet', '🚏|bus stop|stanica', '🛣️|motorway|autoput',
			'⛽|fuel pump|benzin pumpa', '🚨|police cars revolving light|rotacija', '🚥|horizontal traffic light|semafor', '🚧|construction|radovi', '⚓|anchor|sidro', '⛵|sailboat|jedrilica', '🚤|speedboat|gliser', '🛳️|passenger ship|brod', '⛴️|ferry|trajekt',
			'✈️|airplane|avion', '🛫|airplane departure|poletanje', '🛬|airplane arriving|sletanje', '🪂|parachute|padobran', '💺|seat|sediste', '🚁|helicopter|helikopter', '🚀|rocket|raketa', '🛸|flying saucer|nlo', '🚂|steam locomotive|voz lokomotiva',
			'🚆|train|voz', '🚇|metro|metro', '🚉|station|zeleznicka stanica', '🗺️|world map|mapa', '🗿|moyai|moai', '🗽|statue liberty|kip slobode', '🗼|tokyo tower|toranj', '🏰|european castle|zamak', '🏯|japanese castle|zamak japan',
			'🏟️|stadium|stadion', '🎡|ferris wheel|panoramski tocak', '🎢|roller coaster|rolerkoster', '🎠|carousel horse|ringispil', '⛲|fountain|fontana', '⛱️|umbrella on ground|suncobran plaza', '🏖️|beach umbrella|plaza', '🏝️|desert island|ostrvo',
			'🏜️|desert|pustinja', '🌋|volcano|vulkan', '⛰️|mountain|planina', '🏔️|snow capped mountain|planina sneg', '🗻|mount fuji|fudzi', '🏕️|camping|kamp', '⛺|tent|sator', '🏠|house building|kuca', '🏡|house garden|kuca dvoriste',
			'🏘️|house buildings|naselje', '🏚️|derelict house building|napustena kuca', '🏗️|building construction|gradiliste', '🏭|factory|fabrika', '🏢|office building|zgrada', '🏬|department store|robna kuca', '🏣|japanese post office|posta', '🏥|hospital|bolnica', '🏦|bank|banka',
			'🏨|hotel|hotel', '🏪|convenience store|prodavnica', '🏫|school|skola', '💒|wedding|venčanje crkva', '🏛️|classical building|muzej', '⛪|church|crkva', '🕌|mosque|dzamija', '🕍|synagogue|sinagoga', '🛕|hindu temple|hram',
			'🌅|sunrise|izlazak sunca', '🌄|sunrise over mountains|zora planine', '🌆|cityscape at dusk|grad sumrak', '🌇|sunset over buildings|zalazak grad', '🌃|night stars|noc grad', '🌉|bridge at night|most noc', '🌌|milky way|mlecni put',
			'🇷🇸|regional indicator letter r s|srbija zastava', '🇲🇪|regional indicator letter m e|crna gora', '🇧🇦|regional indicator letter b|bosna', '🇭🇷|regional indicator letter h r|hrvatska', '🇲🇰|regional indicator letter m k|makedonija', '🇸🇮|regional indicator letter s i|slovenija', '🇪🇺|regional indicator letter e u|eu', '🏁|chequered flag|cilj zastava'
		]],
		['objects', '💡', [
			'⌚|watch|sat rucni', '📱|mobile phone|telefon mobilni', '💻|personal computer|laptop', '⌨️|keyboard|tastatura', '🖥️|desktop computer|racunar monitor', '🖨️|printer|stampac', '🖱️|three button mouse|mis racunar', '💽|minidisc|disk',
			'💾|floppy disk|disketa', '💿|optical disc|cd', '📀|dvd|dvd', '📷|camera|fotoaparat', '📸|camera flash|fotoaparat blic', '📹|video camera|kamera', '🎥|movie camera|filmska kamera', '📽️|film projector|projektor', '📞|telephone receiver|telefon',
			'☎️|black telephone|telefon stari', '📺|television|televizor', '📻|radio|radio', '🎙️|studio microphone|mikrofon studio', '⏰|alarm clock|budilnik', '⏳|hourglass flowing sand|pescani sat', '⌛|hourglass|pescani sat', '📡|satellite antenna|antena',
			'🔋|battery|baterija', '🔌|electric plug|utikac', '💡|electric light bulb|sijalica ideja', '🔦|electric torch|baterijska lampa', '🕯️|candle|sveca', '🧯|fire extinguisher|aparat gasenje', '🛢️|oil drum|bure', '💸|money wings|novac leti',
			'💵|banknote dollar|dolar novac', '💶|banknote euro|evro', '💰|money bag|vreca novac', '💳|credit card|kartica', '🧾|receipt|racun', '💎|gem stone|dijamant', '⚖️|scales|vaga pravda', '🧰|toolbox|alat kutija', '🔧|wrench|kljuc alat',
			'🔨|hammer|cekic', '⚒️|hammer pick|cekic pijuk', '🛠️|hammer wrench|alat', '⛏️|pick|pijuk', '🔩|nut bolt|zavrtanj', '⚙️|gear|zupcanik', '🧱|brick|cigla', '⛓️|chains|lanac', '🧲|magnet|magnet', '🔫|pistol|pistolj',
			'💣|bomb|bomba', '🧨|firecracker|petarda', '🪓|axe|sekira', '🔪|hocho|noz', '🗡️|dagger knife|mac', '🛡️|shield|stit', '🚬|smoking|cigareta', '⚰️|coffin|kovceg', '🏺|amphora|amfora vaza', '🔮|crystal ball|kristalna kugla',
			'📿|prayer beads|brojanica', '🧿|nazar amulet|nazar', '💈|barber pole|berberin', '⚗️|alembic|alembik', '🔭|telescope|teleskop', '🔬|microscope|mikroskop', '🕳️|hole|rupa', '🩹|adhesive bandage|flaster', '🩺|stethoscope|stetoskop',
			'💊|pill|pilula lek', '💉|syringe|injekcija', '🩸|drop blood|krv kap', '🧬|dna double helix|dnk', '🦠|microbe|virus', '🧫|petri dish|petri', '🧪|test tube|epruveta', '🌡️|thermometer|termometar', '🧹|broom|metla',
			'🧺|basket|korpa ves', '🧻|roll paper|toalet papir', '🚽|toilet|wc', '🚿|shower|tus', '🛁|bathtub|kada', '🧼|bar soap|sapun', '🪥|toothbrush|cetkica zubi', '🧽|sponge|sundjer', '🛒|shopping trolley|kolica kupovina',
			'🚪|door|vrata', '🪑|chair|stolica', '🛋️|couch lamp|sofa', '🛏️|bed|krevet', '🖼️|frame picture|slika ram', '🛍️|shopping bags|kese kupovina', '🎁|wrapped present|poklon', '🎈|balloon|balon', '🎀|ribbon|masna',
			'🎊|confetti ball|konfete', '🎉|party popper|zurka proslava', '🎎|japanese dolls|lutke', '🏮|izakaya lantern|lampion', '🎐|wind chime|zvonce vetar', '✉️|envelope|koverta pismo', '📩|envelope downwards arrow above|mejl', '📦|package|paket', '📫|closed mailbox raised flag|sanduce',
			'📝|memo|beleska pisanje', '📖|open book|knjiga otvorena', '📚|books|knjige', '📰|newspaper|novine', '🗞️|rolled up newspaper|novine', '📅|calendar|kalendar', '📌|pushpin|pribadaca', '📎|paperclip|spajalica',
			'✂️|black scissors|makaze', '🔒|lock|katanac', '🔑|key|kljuc', '🗝️|old key|stari kljuc'
		]],
		['symbols', '❤️', [
			'❤️|heavy black heart|srce crveno ljubav', '🧡|orange heart|srce narandzasto', '💛|yellow heart|srce zuto', '💚|green heart|srce zeleno', '💙|blue heart|srce plavo', '💜|purple heart|srce ljubicasto', '🖤|black heart|srce crno',
			'🤍|white heart|srce belo', '🤎|brown heart|srce braon', '💔|broken heart|slomljeno srce', '❣️|heavy heart exclamation mark ornament|srce uzvicnik', '💕|two hearts|dva srca', '💞|revolving hearts|srca vrte', '💓|beating heart|srce kuca', '💗|growing heart|srce raste',
			'💖|sparkling heart|srce sjaj', '💘|heart arrow|srce strela', '💝|heart ribbon|srce poklon', '💟|heart decoration|srce ukras', '☮️|peace|mir', '✝️|latin cross|krst', '☦️|orthodox cross|pravoslavni krst', '☪️|star crescent|polumesec',
			'🕉️|om|om', '☸️|wheel dharma|dharma', '✡️|star david|davidova zvezda', '🔯|six pointed star middle dot|heksagram', '♈|aries|ovan', '♉|taurus|bik', '♊|gemini|blizanci', '♋|cancer|rak', '♌|leo|lav', '♍|virgo|devica',
			'♎|libra|vaga', '♏|scorpius|skorpija', '♐|sagittarius|strelac', '♑|capricorn|jarac', '♒|aquarius|vodolija', '♓|pisces|ribe', '⛎|ophiuchus|zmijonosac', '🆔|squared id|id', '⚛️|atom|atom', '🉑|circled ideograph accept|prihvatljivo',
			'☢️|radioactive|radioaktivno', '☣️|biohazard|biohazard', '📴|mobile phone off|iskljuceno', '📳|vibration mode|vibracija', '🈶|squared cjk unified ideograph 6709|japanski', '✅|white heavy check mark|kvacica zeleno tacno', '❌|cross mark|iks crveno netacno',
			'❎|negative squared cross mark|iks kvadrat', '✔️|heavy check mark|kvacica', '☑️|ballot box check|kvacica kvadrat', '➕|heavy plus|plus', '➖|heavy minus|minus', '➗|heavy division|podeljeno', '✖️|heavy multiplication x|puta', '♾️|permanent paper|beskonacno', '💯|hundred points|sto poena',
			'‼️|double exclamation mark|dva uzvicnika', '⁉️|exclamation question mark|uzvicnik upitnik', '❓|black question mark ornament|upitnik', '❔|white question mark ornament|upitnik beli', '❗|heavy exclamation mark|uzvicnik', '❕|white exclamation mark ornament|uzvicnik beli', '⚠️|warning|upozorenje', '🚸|children crossing|deca',
			'🔱|trident emblem|trozubac', '⚜️|fleur de lis|ljiljan', '🔰|japanese for beginner|pocetnik', '♻️|black universal recycling|reciklaza', '💲|heavy dollar|dolar', '💱|currency exchange|menjacnica', '©️|copyright|autorsko pravo', '®️|registered|registrovano', '™️|trade mark|zastitni znak',
			'🔴|large red circle|crveni krug', '🟠|large orange circle|narandzasti krug', '🟡|large yellow circle|zuti krug', '🟢|large green circle|zeleni krug', '🔵|large blue circle|plavi krug', '🟣|large purple circle|ljubicasti krug', '⚫|medium black circle|crni krug',
			'⚪|medium white circle|beli krug', '🟥|large red square|crveni kvadrat', '🟩|large green square|zeleni kvadrat', '🟦|large blue square|plavi kvadrat', '⬛|black large square|crni kvadrat', '⬜|white large square|beli kvadrat', '🔶|large orange diamond|narandzasti romb',
			'🔷|large blue diamond|plavi romb', '🔸|small orange diamond|mali romb', '🔹|small blue diamond|mali romb plavi', '🔺|up pointing red triangle|trougao gore', '🔻|down pointing red triangle|trougao dole', '💠|diamond shape dot inside|romb cvet', '🔘|radio button|radio dugme',
			'⬆️|upwards black arrow|strelica gore', '⬇️|downwards black arrow|strelica dole', '⬅️|leftwards black arrow|strelica levo', '➡️|black rightwards arrow|strelica desno', '↗️|north east arrow|strelica gore desno', '↘️|south east arrow|strelica dole desno',
			'↙️|south west arrow|strelica dole levo', '↖️|north west arrow|strelica gore levo', '↕️|up down arrow|strelica gore dole', '↔️|left right arrow|strelica levo desno', '🔄|anticlockwise downwards upwards open circle arrows|osvezi strelice', '🔃|clockwise downwards upwards open circle arrows|strelice',
			'🔀|twisted rightwards arrows|nasumicno', '🔁|clockwise rightwards leftwards open circle arrows|ponavljanje', '▶️|black right pointing triangle|plej', '⏸️|double vertical bar|pauza', '⏹️|black square for stop|stop', '⏺️|black circle for record|snimanje', '⏭️|black right pointing double triangle vertical bar|sledece', '⏮️|black left pointing double triangle vertical bar|prethodno', '🔊|speaker three sound waves|zvuk glasno',
			'🔇|speaker cancellation stroke|bez zvuka', '🔔|bell|zvono', '🔕|bell cancellation stroke|zvono iskljuceno', '📢|public address loudspeaker|megafon', '💬|speech balloon|oblacic govor', '💭|thought balloon|oblacic misao', '🗨️|left speech bubble|oblacic', '♨️|hot springs|vruci izvor',
			'🕐|clock one oclock|jedan sat', '🕑|clock two oclock|dva sata', '🕒|clock three oclock|tri sata', '🕓|clock four oclock|cetiri sata', '🕔|clock five oclock|pet sati', '🕕|clock six oclock|sest sati', '🕖|clock seven oclock|sedam sati', '🕗|clock eight oclock|osam sati',
			'🕘|clock nine oclock|devet sati', '🕙|clock ten oclock|deset sati', '🕚|clock eleven oclock|jedanaest sati', '🕛|clock twelve oclock|dvanaest sati'
		]]
	];

	function norm(s) {
		return (s || '').toLowerCase().replace(/\u0111/g, 'dj')
			.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}

	function getRecent() {
		try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return []; }
	}

	function pushRecent(ch) {
		var r = getRecent().filter(function (x) { return x !== ch; });
		r.unshift(ch);
		try { localStorage.setItem(RECENT_KEY, JSON.stringify(r.slice(0, RECENT_MAX))); } catch (e) {}
	}

	function insertAtCursor(ta, text) {
		ta.focus();
		var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
		if (typeof s === 'number') {
			ta.value = v.slice(0, s) + text + v.slice(e);
			ta.selectionStart = ta.selectionEnd = s + text.length;
		} else {
			ta.value += text;
		}
		// the core character counter listens to keyup
		ta.dispatchEvent(new Event('input', { bubbles: true }));
		ta.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
	}

	function build(editor, ta) {
		var pop = document.createElement('div');
		pop.className = 'rkjc-emoji';
		pop.setAttribute('role', 'dialog');
		pop.setAttribute('aria-label', T.title);
		pop.hidden = true;

		var tabs = document.createElement('div');
		tabs.className = 'rkjc-emoji__tabs';
		var search = document.createElement('input');
		search.type = 'search';
		search.className = 'rkjc-emoji__search';
		search.placeholder = T.search;
		search.setAttribute('aria-label', T.searchLabel);
		var body = document.createElement('div');
		body.className = 'rkjc-emoji__body';

		pop.appendChild(tabs);
		pop.appendChild(search);
		pop.appendChild(body);
		editor.appendChild(pop);

		var cats = [];
		var recent = getRecent();
		if (recent.length) {
			cats.push(['recent', '🕘', recent.map(function (c) { return c + '|'; })]);
		}
		cats = cats.concat(DATA);

		function renderTabs() {
			tabs.innerHTML = '';
			cats.forEach(function (cat, i) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'rkjc-emoji__tab';
				b.title = T.cat[cat[0]] || cat[0];
				b.textContent = cat[1];
				b.setAttribute('data-cat', String(i));
				b.addEventListener('click', function () {
					search.value = '';
					var sec = body.querySelector('[data-sec="' + i + '"]');
					if (sec) { body.scrollTop = sec.offsetTop - body.offsetTop; }
					setActive(i);
				});
				tabs.appendChild(b);
			});
		}

		function setActive(i) {
			tabs.querySelectorAll('.rkjc-emoji__tab').forEach(function (t) {
				t.classList.toggle('is-active', t.getAttribute('data-cat') === String(i));
			});
		}

		function grid(items) {
			var g = document.createElement('div');
			g.className = 'rkjc-emoji__grid';
			items.forEach(function (it) {
				var parts = it.split('|'), ch = parts[0];
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'rkjc-emoji__item';
				b.textContent = ch;
				b.title = (SR && parts[2]) ? parts[2] : (parts[1] || '');
				b.setAttribute('data-emoji', ch);
				g.appendChild(b);
			});
			return g;
		}

		function renderAll() {
			body.innerHTML = '';
			cats.forEach(function (cat, i) {
				var sec = document.createElement('div');
				sec.className = 'rkjc-emoji__sec';
				sec.setAttribute('data-sec', String(i));
				var h = document.createElement('div');
				h.className = 'rkjc-emoji__title';
				h.textContent = T.cat[cat[0]] || cat[0];
				sec.appendChild(h);
				sec.appendChild(grid(cat[2]));
				body.appendChild(sec);
			});
			setActive(0);
		}

		function renderSearch(q) {
			var nq = norm(q), hits = [];
			DATA.forEach(function (cat) {
				cat[2].forEach(function (it) {
					if (norm(it.slice(it.indexOf('|') + 1)).indexOf(nq) !== -1) { hits.push(it); }
				});
			});
			body.innerHTML = '';
			var sec = document.createElement('div');
			sec.className = 'rkjc-emoji__sec';
			var h = document.createElement('div');
			h.className = 'rkjc-emoji__title';
			h.textContent = hits.length ? T.results.replace('%d', hits.length) : T.noResults;
			sec.appendChild(h);
			sec.appendChild(grid(hits.slice(0, 120)));
			body.appendChild(sec);
		}

		search.addEventListener('input', function () {
			var q = search.value.trim();
			if (q.length >= 2) { renderSearch(q); } else { renderAll(); }
		});

		body.addEventListener('click', function (e) {
			var b = e.target.closest('.rkjc-emoji__item');
			if (!b) { return; }
			e.preventDefault();
			var ch = b.getAttribute('data-emoji');
			insertAtCursor(ta, ch);
			pushRecent(ch);
			if (!e.shiftKey) { close(); }
		});

		body.addEventListener('scroll', function () {
			if (search.value.trim().length >= 2) { return; }
			var top = body.scrollTop + 8, cur = 0;
			body.querySelectorAll('.rkjc-emoji__sec').forEach(function (s, i) {
				if (s.offsetTop - body.offsetTop <= top) { cur = i; }
			});
			setActive(cur);
		});

		function open() {
			// refresh "Recent"
			var r = getRecent();
			if (r.length) {
				if (cats[0][0] === 'recent') { cats[0][2] = r.map(function (c) { return c + '|'; }); }
				else { cats.unshift(['recent', '🕘', r.map(function (c) { return c + '|'; })]); }
			}
			renderTabs();
			renderAll();
			search.value = '';
			pop.hidden = false;
			requestAnimationFrame(function () { pop.classList.add('is-open'); search.focus(); });
			document.addEventListener('mousedown', outside, true);
			document.addEventListener('keydown', esc, true);
		}

		function close() {
			pop.classList.remove('is-open');
			setTimeout(function () { pop.hidden = true; }, 150);
			document.removeEventListener('mousedown', outside, true);
			document.removeEventListener('keydown', esc, true);
		}

		function outside(e) {
			if (!pop.contains(e.target) && !e.target.closest('.bbcode-smile')) { close(); }
		}

		function esc(e) {
			if (e.key === 'Escape') { e.preventDefault(); close(); ta.focus(); }
		}

		return { open: open, close: close, el: pop };
	}

	function attach(btn) {
		var editor = btn.closest('.rkjc-editor');
		var ta = editor && editor.querySelector('textarea');
		if (!editor || !ta) { return; }
		btn.setAttribute('data-rk-emoji', '1');
		var picker = build(editor, ta);
		btn.onclick = function () {
			if (picker.el.hidden) { picker.open(); } else { picker.close(); }
			return false;
		};
	}

	function scan() {
		document.querySelectorAll('#jc .bbcode a.bbcode-smile:not([data-rk-emoji])').forEach(attach);
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); } else { scan(); }
	window.addEventListener('load', scan);
	new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });

	/* ------------------------------------------------------------------
	   Page in a window (e.g. the privacy policy): <a data-rkjc-iframe href="...?tmpl=component">
	   ------------------------------------------------------------------ */
	var frameModal = null;

	function openFrame(href, title) {
		if (!frameModal) {
			frameModal = document.createElement('div');
			frameModal.className = 'rkjc-frame-modal';
			frameModal.setAttribute('role', 'dialog');
			frameModal.setAttribute('aria-modal', 'true');
			frameModal.innerHTML =
				'<div class="rkjc-frame-modal__backdrop" data-rkjc-frame-close></div>' +
				'<div class="rkjc-frame-modal__box">' +
				'<div class="rkjc-frame-modal__head"><div class="rkjc-frame-modal__title"></div>' +
				'<button type="button" class="rkjc-frame-modal__close" data-rkjc-frame-close aria-label="' + esc(T.close) + '">&times;</button></div>' +
				'<iframe class="rkjc-frame-modal__frame" title="" loading="lazy" referrerpolicy="same-origin"></iframe>' +
				'</div>';
			document.body.appendChild(frameModal);
		}
		frameModal.querySelector('.rkjc-frame-modal__title').textContent = title || '';
		var fr = frameModal.querySelector('iframe');
		fr.title = title || '';
		fr.src = href;
		frameModal.hidden = false;
		document.documentElement.classList.add('rkjc-modal-open');
		requestAnimationFrame(function () { frameModal.classList.add('is-open'); });
	}

	function closeFrame() {
		if (!frameModal || frameModal.hidden) { return; }
		frameModal.classList.remove('is-open');
		document.documentElement.classList.remove('rkjc-modal-open');
		setTimeout(function () { frameModal.hidden = true; frameModal.querySelector('iframe').src = 'about:blank'; }, 180);
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest('#jc a[data-rkjc-iframe]');
		if (a) { e.preventDefault(); openFrame(a.getAttribute('href'), a.getAttribute('title') || a.textContent.trim()); return; }
		if (e.target.closest('[data-rkjc-frame-close]')) { e.preventDefault(); closeFrame(); }
	});
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeFrame(); } });
})();

/**
 * Invisible reCAPTCHA + JComments AJAX submit.
 * The Joomla captcha plugin runs the check once at page load and the token lives two minutes; JComments sends the
 * comment through AJAX, so a new check never starts ("Empty solution not allowed"). Here a fresh check (reset +
 * execute) runs before every submit, the token is awaited, and only then the JComments button is let through.
 */
(function () {
	'use strict';

	var I18N = window.rkjcI18n || {};
	var CAPTCHA_TIMEOUT = I18N.captchaTimeout || 'The reCAPTCHA check did not complete (no answer within a minute). Reload the page and try again.';

	function widgetId() {
		var el = document.querySelector('#comments-form .g-recaptcha');
		if (!el) { return null; }
		var id = el.getAttribute('data-recaptcha-widget-id');
		if (id !== null && id !== '') { return id; }
		// The form arrived through AJAX after page load ("show form on click" with comments), so the Joomla onload
		// (JoomlainitReCaptchaInvisible) has already run and this widget was never rendered. Render it here, same options.
		if (typeof grecaptcha.render !== 'function') { return null; }
		try {
			var o = {};
			['sitekey', 'badge', 'size', 'tabindex', 'callback', 'expired-callback', 'error-callback'].forEach(function (k) {
				var v = el.getAttribute('data-' + k);
				if (v !== null && v !== '') { o[k] = v; }
			});
			if (!o.sitekey) { return null; }
			o.size = 'invisible';
			id = String(grecaptcha.render(el, o));
			el.setAttribute('data-recaptcha-widget-id', id);
			return id;
		} catch (x) {
			return null;
		}
	}

	document.addEventListener('click', function (e) {
		var btn = e.target && e.target.id === 'comments-form-send' ? e.target : null;
		if (!btn || !window.grecaptcha || typeof grecaptcha.execute !== 'function') { return; }
		var id = widgetId();
		if (id === null) { return; }
		if (btn.dataset.rkCaptchaOk === '1') { btn.dataset.rkCaptchaOk = ''; return; } // the token is fresh, let JComments proceed

		e.preventDefault();
		e.stopImmediatePropagation();
		btn.disabled = true;
		try { grecaptcha.reset(id); } catch (x) { /* not important */ }
		try { grecaptcha.execute(id); } catch (x) { btn.disabled = false; return; }

		var started = Date.now();
		(function wait() {
			var token = '';
			try { token = grecaptcha.getResponse(id); } catch (x) { /* not ready yet */ }
			if (token) {
				btn.disabled = false;
				btn.dataset.rkCaptchaOk = '1';
				btn.click();
			} else if (Date.now() - started < 60000) {
				setTimeout(wait, 150);
			} else {
				btn.disabled = false;
				window.alert(CAPTCHA_TIMEOUT);
			}
		})();
	}, true);
})();
