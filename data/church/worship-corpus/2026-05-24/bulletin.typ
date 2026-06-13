#import "/template/bulletin.typ": *

#show: bulletin.with(
  service-date: "2026-05-24",
  liturgical-day: "Pentecost Sunday",
  cover-image: image("/assets/pentecost.png"),

  order-of-service: [
    #section("Gathering")

    #prelude("Come, Spirit, Come", performer: "Elisabeth Ellis, pianist")
    #spoken("Greeting and Welcome", leader: "Pastor Elizabeth")
    #anthem(
      "Special Music",
      "Come Down, O Love Divine",
      composer: "Ralph Vaughan Williams, arr. Raney",
      performers: "Sanctuary Choir; Elisabeth Ellis, pianist and director",
    )
    #responsive(
      "Responsive Call to Worship",
      leader: "Claire Gebben",
      standing: true,
      congregation: [
        As we gather here, we pray: \
        *Come, Holy Spirit, come!*

        Renew the face of the earth. \
        *Come, Holy Spirit!*

        Renew our lives. \
        *Come, Holy Spirit!*

        Renew our faith. \
        *Come, Holy Spirit!*

        Challenge us, comfort us, \
        *Stir us up, calm us down.*

        Gather us in; send us out. \
        *Rest upon us and within us.*
      ],
    )

    #spoken("Opening Prayer", leader: "Claire Gebben", standing: true)
    #hymn(
      "Opening Hymn",
      "O Spirit of the Living God (v. 1, 3, 4)",
      hymnal: "UMH #539",
      standing: true,
    )

    #section("Proclaiming")

    #spoken("Prayer for Illumination", leader: "Mona Tanaka")
    #scripture("Gospel Reading", "John 7:37-39", reader: "Mona Tanaka")
    #scripture("Second Reading", "Acts 2:1-21", reader: "Ann Morgan")
    #sermon("What We Need Is the Spirit", preacher: "Pastor Elizabeth")

    #section("Responding")

    #instruction(
      "Moment for Reflection",
      "Take a moment now to reflect, pray, or simply be still.",
    )

    #spoken("Pastoral Prayer", leader: "Pastor Elizabeth")
    #spoken("Passing of the Peace", leader: "Pastor Elizabeth")
    #spoken("The Offering", leader: "Claire Gebben")
    #anthem(
      "Offertory Music",
      "Every Time I Feel the Spirit",
      composer: "traditional spiritual, arr. Blozan",
      performers: "Ibidunni Ojikutu, soloist; Elisabeth Ellis, pianist",
      indent: true,
    )
    #hymn(
      "Doxology",
      "Praise God, from Whom All Blessings Flow",
      hymnal: "UMH #94",
      standing: true,
      indent: true,
    )
    #spoken("Prayer of Dedication", leader: "Pastor Elizabeth", standing: true, indent: true)
    #spoken("The Sacrament of Holy Communion", leader: "Pastor Elizabeth")

    #section("Sending Forth")

    #spoken("Call to Discipleship", leader: "Pastor Jackie")
    #hymn(
      "Closing Hymn",
      "In the Midst of New Dimensions (v. 1-3, 5)",
      hymnal: "TFWS #2238",
    )
    #spoken("Benediction", leader: "Pastor Elizabeth")
    #hymn("Song of Blessing", "The Lord Bless and Keep You", hymnal: "TFWS #2280")

    #postlude("Allegro con spirito", composer: "Alfred Rawlings", performer: "Elisabeth Ellis, organist")

    #service-credits[
      \*\*Our thanks to those helping in worship: Worship Leader, Claire Gebben; Scripture Readers, Mona Tanaka, Ann Morgan; Communion Steward, Jane Gregg; Altar Guild, Craig Mathews; Front Door Greeter, Bruce Hall; Audio-Visual Operator, Laura Celin \
       \
      Today's prayer of dedication is adapted from umcdiscipleship.org.
    ]
  ],

  announcements-body: [
    #announcement(
      "Adult Spirituality Group: Loneliness | Sundays, May 31 & June 7:",
      [Loneliness — we know it's a problem, but what is it, and what can we do about it? Join the adult spirituality group in Room 301 at 9:00 a.m. on May 31 and June 7 to talk through this challenging subject together.],
    )

    #announcement(
      "Help Tent City 3 Move! | Tuesday, May 26:",
      [Tent City 3 is moving from County land at 2720 S. Hanford to University Congregational United Church of Christ on Tuesday, May 26 — the day after Memorial Day — and help is sorely needed. Pastor Jackie is putting together a First Church group to join the effort.],
    )

    #upcoming((
      ("May 26", [Tent City 3 Move]),
      ("May 28", [Book Club, 7:00 p.m. on Zoom]),
      ("May 31", [Adult Spirituality: Loneliness — Room 301, 9:00 a.m.]),
      ("May 31", [Welcoming New Members]),
      ("June 7", [Adult Spirituality: Loneliness — Room 301, 9:00 a.m.]),
      ("June 14", [Recognizing Graduates]),
      ("June 21", [Music Sunday]),
      ("June 28", [Pride Sunday!]),
    ))
  ],
)
