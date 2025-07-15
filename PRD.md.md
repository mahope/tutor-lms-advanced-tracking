
# 📄 Product Requirements Document (PRD)
**Projekt:** Advanced Tutor LMS Stats Dashboard  
**Version:** 1.1  
**Ansvarlig:** Mads Holst Jensen  
**Dato:** 15. juli 2025

---

## 🎯 Formål
Udvikle et WordPress-plugin, der udvider Tutor LMS Pro med et frontend-dashboard med **avanceret statistik** og detaljeret indblik i kursus- og brugerdata.

---

## 🧑‍💻 Målgrupper
- **Admins**: Se alt  
- **Instructors**: Se egne kurser og studerende

---

## 🖥️ Funktionelle krav

### 🔐 Adgang og roller
- Dashboard tilgås via `[tutor_advanced_stats]`
- Adgang kræver login
- Roller:
  - **Administrator**: fuld adgang til alt
  - **tutor_instructor**: kun adgang til egne kurser og tilknyttede brugere

---

## 📊 Statistikfunktioner (Udvidet)

### 🔹 Dashboard (kursusoversigt)
- Kursusnavn
- Antal studerende
- Gennemsnitlig progression (%)
- Gennemsnitlig quizscore (%)
- Klikbart link: **“Se detaljer”**

---

### 🔹 Kursusdetaljevisning (NY)
Vises når man klikker “Se detaljer” på et kursus.

#### For hvert kursus:
- Liste over alle tilmeldte studerende
- For hver studerende:
  - Navn + profil-link
  - Progression i % (fx 54 % gennemført)
  - Dato for sidste aktivitet
  - Gennemsnitlig quizscore
  - Klikbart link: “Se elevens detaljer”

#### Quizdetaljer:
- Liste over alle quizzer i kurset
- For hver quiz:
  - Antal gennemførte forsøg
  - Gennemsnitlig score
  - Rigtige vs. forkerte svar pr. spørgsmål
  - Valgte svar og korrekte svar

---

### 🔹 Brugeropslag (NY)
Mulighed for at søge på studerende og få en samlet visning.

#### For hver bruger:
- Liste over alle tilmeldte kurser
- Progression i hvert kursus
- Quizresultater:
  - Score pr. quiz
  - Forkerte svar (opdelt på emner/spørgsmål)
- Notifikation om lav aktivitet (fx inaktiv i 7+ dage)
- Fremhæv områder med mange forkerte svar
- Total gennemførte kurser
- Certifikater opnået

---

## 🔎 Søgefunktioner
- Søg på kursusnavn
- Søg på brugernavn eller e-mail
- Filtrér på status: aktiv / inaktiv / gennemført / drop-out

---

## 💡 Features roadmap (efter v1.1)
- CSV-eksport af kursus- eller brugerdata
- Interaktive grafer (progression over tid)
- REST API til ekstern brug (f.eks. til mobilapp)
- Automatisk advarsler ved lav aktivitet
- Automatisk elevprofiler med forslag til forbedring

---

## 🛠️ Teknisk arkitektur (opdateret)
- **Shortcode**: `[tutor_advanced_stats]`
- **Frontend dashboard**:
  - Kursusoversigt
  - Detaljevisning (kursus)
  - Detaljevisning (bruger)
- Data hentes via kombination af:
  - `Tutor LMS Pro` helper-funktioner
  - `wpdb` queries mod relevante tabeller
- Resultater vises i custom views med let styling
- Mulighed for at udvide med REST API endpoints

---

## 🧪 Test cases (tilføjet)

| Test | Forventet resultat |
|------|--------------------|
| Klik på kursus → vis detaljer | Viser alle studerende og deres data |
| Klik på bruger → vis detaljer | Viser tilmeldte kurser og præstationer |
| Quiz med mange spørgsmål | Viser svarstatistik og fejlområder |
| Bruger uden aktivitet | Markeres som inaktiv |
| Forkert rolle | Ingen adgang til ikke-ejede kurser |

---

## ✅ Done-kriterier
- Shortcode virker og viser kursusliste
- Klik ind på kurser og brugere virker
- Statistik er korrekt udregnet
- UI er overskueligt og mobilt tilpasset
- Koden er veldokumenteret og klar til udvidelse
