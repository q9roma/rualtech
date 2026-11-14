import type * as React from 'react';
function Scene({ children }: { children: React.ReactNode }) {
  return (
    <svg
      viewBox="0 0 320 160"
      preserveAspectRatio="xMidYMax meet"
      className="storefront-task-scene"
    >
      {children}
    </svg>
  )
}

// На чёрном корпуса техники светлее фона и с тонким кантом, иначе они пропадают
const SHELL = "#2e3036"
const EDGE = "#50535b"
const GLYPH = "#222326"
const BODY = "#d7d9dd"

/** Рабочее место: системный блок, монитор, клавиатура, мышь */
export function WorkplaceScene() {
  return (
    <Scene>
      <defs>
        <linearGradient id="dark-sc-w" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#c9dbff" /><stop offset="1" stopColor="#eef3ff" />
        </linearGradient>
      </defs>
      {/* системный блок */}
      <rect x="44" y="62" width="40" height="98" rx="6" fill={SHELL} stroke={EDGE} />
      <circle cx="64" cy="76" r="3" fill="#7fa8ff" />
      <rect x="54" y="90" width="20" height="3" rx="1.5" fill={EDGE} />
      <rect x="54" y="98" width="20" height="3" rx="1.5" fill={EDGE} />
      {/* монитор */}
      <rect x="152" y="112" width="16" height="30" fill="#bfc2c7" />
      <rect x="126" y="140" width="68" height="7" rx="3.5" fill={BODY} />
      <rect x="92" y="18" width="136" height="96" rx="8" fill={SHELL} stroke={EDGE} />
      <rect x="98" y="24" width="124" height="80" rx="4" fill="url(#dark-sc-w)" />
      <rect x="110" y="36" width="58" height="42" rx="5" fill="#fff" />
      <rect x="118" y="45" width="30" height="5" rx="2.5" fill={GLYPH} />
      <rect x="118" y="56" width="42" height="3" rx="1.5" fill="#cfd4dc" />
      <rect x="118" y="64" width="34" height="3" rx="1.5" fill="#cfd4dc" />
      <rect x="176" y="36" width="34" height="18" rx="5" fill="#fff" opacity=".75" />
      <rect x="176" y="60" width="34" height="18" rx="5" fill="#7fa8ff" />
      {/* клавиатура и мышь */}
      <rect x="110" y="151" width="100" height="9" rx="3" fill="#f6f7f8" stroke="#cfd2d6" />
      <path d="M232 160v-3a8 8 0 0 1 16 0v3Z" fill="#f6f7f8" stroke="#cfd2d6" />
    </Scene>
  )
}

/** Работа из дома: ноутбук, растение, кружка */
export function HomeScene() {
  return (
    <Scene>
      <defs>
        <linearGradient id="dark-sc-h" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#ffdcb8" /><stop offset="1" stopColor="#fff6ea" />
        </linearGradient>
      </defs>
      {/* растение */}
      <g fill="#3f9b63">
        <ellipse cx="56" cy="94" rx="9" ry="24" transform="rotate(-24 56 94)" />
        <ellipse cx="74" cy="92" rx="9" ry="26" transform="rotate(18 74 92)" />
        <ellipse cx="65" cy="86" rx="8" ry="28" fill="#4fb478" />
      </g>
      <path d="M46 122h38l-5 38H51Z" fill="#e59a63" />
      <rect x="43" y="118" width="44" height="8" rx="3" fill="#ecab7a" />
      {/* ноутбук */}
      <rect x="100" y="62" width="128" height="90" rx="8" fill={SHELL} stroke={EDGE} />
      <rect x="106" y="68" width="116" height="76" rx="4" fill="url(#dark-sc-h)" />
      <rect x="118" y="80" width="42" height="6" rx="3" fill={GLYPH} />
      <rect x="118" y="94" width="78" height="3" rx="1.5" fill="#e3c9ad" />
      <rect x="118" y="102" width="66" height="3" rx="1.5" fill="#e3c9ad" />
      <rect x="118" y="110" width="72" height="3" rx="1.5" fill="#e3c9ad" />
      <rect x="118" y="122" width="34" height="12" rx="6" fill="#ff9d4d" />
      <path d="M84 152h160v3a5 5 0 0 1-5 5H89a5 5 0 0 1-5-5Z" fill={BODY} />
      <rect x="148" y="152" width="32" height="3" rx="1.5" fill="#bfc2c7" />
      {/* кружка */}
      <path d="M282 137h4a7.5 7.5 0 0 1 0 15h-4" fill="none" stroke="#dcdfe4" strokeWidth="3" />
      <path d="M256 130h26v24a6 6 0 0 1-6 6h-14a6 6 0 0 1-6-6Z" fill="#fff" stroke="#dcdfe4" />
      <path d="M264 122c-4-6 4-8 0-14M274 122c-4-6 4-8 0-14" fill="none" stroke="#8a8e96" strokeWidth="2.5" strokeLinecap="round" opacity=".8" />
    </Scene>
  )
}

/** Переговорная: экран с участниками звонка, камера, спикерфон на столе */
export function MeetingScene() {
  const tiles = [
    { x: 80, y: 18, fill: "#bfe8cf" },
    { x: 162, y: 18, fill: "#e3f5ea" },
    { x: 80, y: 66, fill: "#e3f5ea" },
    { x: 162, y: 66, fill: "#a9dcc0" },
  ]
  return (
    <Scene>
      <rect x="72" y="10" width="176" height="108" rx="8" fill={SHELL} stroke={EDGE} />
      {tiles.map((t) => (
        <g key={`${t.x}-${t.y}`}>
          <rect x={t.x} y={t.y} width="78" height="44" rx="4" fill={t.fill} />
          <circle cx={t.x + 39} cy={t.y + 17} r="8" fill="#fff" />
          <path d={`M${t.x + 23} ${t.y + 44}a16 14 0 0 1 32 0Z`} fill="#fff" />
        </g>
      ))}
      {/* камера под экраном */}
      <rect x="128" y="122" width="64" height="9" rx="4.5" fill={SHELL} stroke={EDGE} />
      <circle cx="160" cy="126.500" r="2.6" fill="#8fd7ac" />
      {/* стол и спикерфон */}
      <path d="M144 148a16 6 0 0 1 32 0Z" fill={SHELL} stroke={EDGE} />
      <circle cx="160" cy="144.500" r="1.6" fill="#5fe07a" />
      <rect x="36" y="148" width="248" height="12" rx="6" fill="#fff" stroke="#dcdfe4" />
    </Scene>
  )
}

/** Сеть для офиса: точка доступа в центре и подключённые устройства */
export function NetworkScene() {
  const nodes = [
    { x: 30, y: 98, kind: "laptop" },
    { x: 246, y: 98, kind: "phone" },
    { x: 66, y: 18, kind: "printer" },
    { x: 210, y: 18, kind: "monitor" },
  ] as const
  return (
    <Scene>
      <circle cx="160" cy="84" r="44" fill="none" stroke="#3f6f9c" strokeWidth="1.5" opacity=".9" />
      <circle cx="160" cy="84" r="70" fill="none" stroke="#3f6f9c" strokeWidth="1.5" opacity=".6" />
      <g stroke="#6fb4ee" strokeWidth="1.5" strokeDasharray="3 5" strokeLinecap="round">
        {nodes.map((n) => (
          <path key={n.kind} d={`M160 84L${n.x + 22} ${n.y + 22}`} />
        ))}
      </g>
      {nodes.map((n) => (
        <g key={n.kind} transform={`translate(${n.x} ${n.y})`}>
          <rect width="44" height="44" rx="13" fill="#fff" stroke="#dcdfe4" />
          {n.kind === "laptop" && (<><rect x="12" y="13" width="20" height="14" rx="2.5" fill={GLYPH} /><rect x="8" y="29" width="28" height="3" rx="1.5" fill={GLYPH} /></>)}
          {n.kind === "phone" && (<><rect x="15" y="9" width="14" height="26" rx="3.5" fill={GLYPH} /><rect x="19" y="30" width="6" height="1.8" rx=".9" fill="#fff" /></>)}
          {n.kind === "printer" && (<><rect x="15" y="9" width="14" height="9" fill="#c9ccd0" /><rect x="10" y="16" width="24" height="14" rx="3" fill={GLYPH} /><rect x="15" y="26" width="14" height="9" fill="#e6e8eb" stroke="#c9ccd0" /></>)}
          {n.kind === "monitor" && (<><rect x="10" y="10" width="24" height="17" rx="3" fill={GLYPH} /><rect x="20" y="27" width="4" height="5" fill={GLYPH} /><rect x="15" y="32" width="14" height="2.5" rx="1.25" fill={GLYPH} /></>)}
        </g>
      ))}
      {/* точка доступа */}
      <rect x="134" y="58" width="52" height="52" rx="16" fill="#fff" stroke="#dcdfe4" />
      <path d="M149 82a15 15 0 0 1 22 0M154 88a8.500 8.500 0 0 1 12 0" fill="none" stroke="#3fa0f0" strokeWidth="3" strokeLinecap="round" />
      <circle cx="160" cy="94.500" r="2.800" fill="#3fa0f0" />
    </Scene>
  )
}

