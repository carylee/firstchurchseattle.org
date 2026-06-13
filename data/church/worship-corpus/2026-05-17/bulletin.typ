#import "/template/bulletin.typ": *

#show: bulletin.with(
  service-date: "2026-05-17",
  liturgical-day: "Seventh Sunday of Easter",
  // Defaults to the stained-glass photo, so this is only here as an example
  // of how to override it. Comment it out for the default.
  cover-image: image("/assets/cover-stained-glass.png"),

  order-of-service: [
    #section("Gathering")

    #prelude("Let Us Be Bread", performer: "Elisabeth Ellis, pianist")
    #spoken("Greeting and Welcome", leader: "Pastor Elizabeth")
    #responsive(
      "Responsive Call to Worship",
      leader: "Erin Elaine Williams",
      standing: true,
      congregation: [
        We gather as hungry people: \
        *hungry for fellowship,* \
        *hungry for Good News,* \
        *hungry for the Bread of Life.*

        We come, trusting that we will be fed: \
        *nourished by friendship,* \
        *nourished by your Word,* \
        *nourished by worship.*

        Open our hearts to receive your love, \
        *that we might become bread for a hungry world.*
      ],
    )

    #spoken("Opening Prayer", leader: "Erin Elaine Williams", standing: true)
    #hymn("Opening Hymn", "I Come with Joy", hymnal: "UMH #617", standing: true)

    #section("Proclaiming")

    #spoken("Prayer for Illumination", leader: "Bruce Hall")
    #scripture("Epistle Reading", "1 Corinthians 11:23-26", reader: "Bruce Hall")
    #anthem(
      "Special Music",
      "10,000 Reasons (Bless the Lord, O My Soul)",
      composer: "arr. Sorensen",
      performers: "Sanctuary Choir; Elisabeth Ellis, pianist and director",
    )
    #scripture("Gospel Reading", "Matthew 26:26-30", reader: "Debra Loacker")
    #sermon("What We Need Is Bread", preacher: "Pastor Elizabeth")

    #section("Responding")

    #instruction(
      "Moment for Reflection",
      "Take a moment now to reflect, pray, or simply be still.",
    )

    #spoken("Pastoral Prayer", leader: "Pastor Jackie")
    #spoken("Passing of the Peace", leader: "Pastor Jackie")
    #spoken("The Offering", leader: "Erin Elaine Williams")
    #anthem(
      "Offertory Music",
      "Let Us Break Bread Together",
      composer: "arr. John Carter",
      performers: "Amy Van Mechelen, soloist; Elisabeth Ellis, pianist",
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
    #hymn("Closing Hymn", "You Feed Us, Gentle Savior", hymnal: "W&S #3169")
    #spoken("Benediction", leader: "Pastor Elizabeth")
    #hymn("Song of Blessing", "The Lord Bless and Keep You", hymnal: "TFWS #2280")

    #postlude(
      "Improvisation on \"One Bread, One Body\"",
      performer: "Elisabeth Ellis, organist",
    )

    #service-credits[
      \*\*Our thanks to those helping in worship: Worship Leader, Erin Elaine Williams; Scripture Readers, Bruce Hall, Debra Loacker; Communion Steward, Peter Jabin; Altar Guild, Craig Matthews; Front Door Greeter, Beth Snyder; Audio-Visual Operator, Laura Celin
    ]
  ],

  announcements-body: [
    #announcement(
      "Children & Youth Activity: Artwalk/Labyrinth at Seattle Center | TODAY after Worship:",
      [Children, youth, and families are invited to take a walk around Seattle Center's sculptures and artworks, followed by fellowship time at the playground and labyrinth next to MOPOP. Parents/Guardians may drop off their kids and attend the Lunch and Learn (see below).],
    )

    #announcement(
      "First Church Forward Lunch & Learn | TODAY after Worship:",
      [You are invited to attend an all-church Lunch & Learn today after worship in the Fellowship Hall. The purpose of this meeting is to provide an update, and to get your feedback, on the work to date of the First Church Forward (FCF) team.],
    )

    #upcoming((
      ("May 17", [Lunch & Learn: All-Church Forward after Worship]),
      ("May 17", [Children & Youth Activity: Artwalk/Labyrinth at Seattle Center after Worship]),
      ("May 24", [Pentecost Sunday! Wear red!]),
    ))
  ],
)
