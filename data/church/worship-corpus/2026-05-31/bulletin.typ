#import "/template/bulletin.typ": *

#show: bulletin.with(
  service-date: "2026-05-31",
  liturgical-day: "Trinity Sunday/Peace with Justice Sunday",

  order-of-service: [
    #section("Gathering")

    #prelude("They’ll Know We Are Christians By Our Love", performer: "Elisabeth Ellis, pianist", composer: "arr. Elisabeth Ellis")
    #spoken("Greeting and Welcome", leader: "Pastor Elizabeth")
    #anthem(
      "Special Music",
      "For Everyone Born",
      composer: "arr. Trenney",
      performers: "Sanctuary Choir; Elisabeth Ellis, Director and Pianist",
    )
    #responsive(
      "Responsive Call to Worship",
      leader: "Barbara Moreland",
      standing: true,
      congregation: [
        Creator God, our source and our home, we belong to you. \
        *We gather to worship and praise you.* \
        Living Christ, you establish justice, overthrow evil, and bless the downtrodden, even at the expense of your life. \
        *We gather to thank you and join you in ministry.* \
        Spirit of mercy, you give us courage and compassion to work for justice and bear witness to your grace. \
        *We gather to be changed by your power.* \
        *Transform us by your grace, that we may serve you in humility, gentleness, and courage!*
      ],
    )
    #spoken("Opening Prayer", leader: "Barbara Moreland", standing: true)
    #hymn("Opening Hymn", "God of Grace and God of Glory", hymnal: "UMH #577", standing: true)

    #section("Proclaiming")

    #spoken("Prayer for Illumination", leader: "Wally Shaffer")
    #scripture("Hebrew Bible Reading", "Isaiah 58:1-12", reader: "Wally Shaffer")
    #scripture("Gospel Reading", "Luke 10:25-37", reader: "Ann Shaffer")
    #sermon("What We Need Is Justice", preacher: "Pastor Elizabeth")

    #section("Responding")

    #instruction(
      "Moment for Reflection",
      "Take a moment now to reflect, pray, or simply be still.",
    )
    #spoken("Pastoral Prayer", leader: "Pastor Kathy")
    #spoken("Passing of the Peace", leader: "Pastor Kathy")
    #spoken("The Offering", leader: "Barbara Moreland")
    #anthem(
      "Offertory Music",
      "I Dream of a World",
      composer: "Mark Miller",
      performers: "Cary Lee, soloist; Elisabeth Ellis, pianist",
      indent: true,
    )
    #hymn(
      "Doxology",
      "Praise God, from Whom All Blessings Flow",
      hymnal: "UMH #94",
      standing: true,
      indent: true,
    )
    #responsive(
      "Prayer of Dedication",
      leader: "Pastor Elizabeth",
      standing: true,
      indent: true,
      congregation: [
        *All that we have comes from you, O God. We thank you for the gifts in our lives, and offer a portion of those gifts, in gratitude, for the work of the church. Bless and multiply them, we pray, that they might build up ancient ruins and raise up the foundations of generations, repairing and restoring all the places your children live. Amen.*
      ],
    )
    #spoken("The Sacrament of Holy Communion", leader: "Pastor Elizabeth")
    #spoken("Welcoming New Members", leader: "Pastors")

    #section("Sending Forth")

    #spoken("Call to Discipleship", leader: "Pastor Jackie")
    #hymn("Closing Hymn", "O Young and Fearless Prophet", hymnal: "UMH #444")
    #spoken("Benediction", leader: "Pastor Elizabeth")
    #hymn("Song of Blessing", "The Lord Bless and Keep You", hymnal: "TFWS #2280")
    #postlude("Improvisation on \"Where Charity and Love Prevail\"", performer: "Elisabeth Ellis, organist")

    #service-credits[
      \*\*Our thanks to those helping in worship: Worship Leader, Barbara Moreland; Scripture Readers, Ann Shaffer, Wally Shaffer; Altar Guild, Craig Mathews; Communion Steward, Teresa Canady; Audio-Visual Operator, Laura Celin; Audio-Visual Support, Steve Morse; Greeters, Eleanor and Keith Schubert

      \ Today’s Call to Worship is adapted from “Creator God, Our Source and Our Home” by Steve Garnaas-Holmes, 2025. The communion liturgy is adapted from Wells and Kocher, *Eucharistic Prayers*, 2016.
    ]
  ],

  announcements-body: [
    #announcement(
      "Peace with Justice Special Offering | Sunday, May 31:",
      [This Sunday is Peace with Justice Sunday in the UMC. Your gift to this special offering supports programs working toward a faithful, just, and peaceful world, in our region and around the globe.],
    )
    #announcement(
      "Singing the Archipelago | Sunday, May 31, 3:00 p.m. at Bothell UMC:",
      [Soprano Tess Altiveros, baritone José Rubio and pianist Elisabeth Ellis will present a recital of classical song from the Philippines. Free with RSVP requested.],
    )
    #announcement(
      "Women's Lunch | Tuesday, June 2, 11:30 a.m.:",
      [Women are invited to gather at the Seattle Center Armory to deepen relationships and build community. Bring your lunch or buy it there; we'll meet near the Ceres Roasting Company. Questions? Contact Janet Skinner.],
    )
    #announcement(
      "Calling All Seniors! | June 14th:",
      [Celebrating a graduation or a 5th- or 8th-grade promotion? Contact Pastor Jackie so graduates can be recognized in worship on June 14.],
    )
    #announcement(
      "Open Mic Night | Thursday, June 11, 6:00 p.m.:",
      [First Church hosts another Open Mic Night in the Sanctuary. Perform a favorite piece or just come enjoy the show.],
    )
    #announcement(
      "Farewell Reception for Pastors Elizabeth & Kathy | Sunday, June 21:",
      [Join us after worship for a farewell reception honoring our interim pastors. Grab a slice of cake, sign their digital thank-you boards, and help us express our gratitude for their ministry.],
    )

    #upcoming((
      ("May 31", [Singing the Archipelago — Bothell UMC, 3:00 p.m.]),
      ("June 2", [Women's Lunch — Seattle Center Armory, 11:30 a.m.]),
      ("June 7", [Adult Spirituality: Loneliness — Room 301, 9:00 a.m.]),
      ("June 11", [Open Mic Night — Sanctuary, 6:00 p.m.]),
      ("June 14", [Recognizing Graduates]),
      ("June 21", [Music Sunday; Farewell Reception after worship]),
      ("June 28", [Pride Sunday!]),
    ))
  ],
)
