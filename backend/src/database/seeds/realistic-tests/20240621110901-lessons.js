"use strict";

/** @type {import('sequelize-cli').Migration} */
module.exports = {
  async up(queryInterface, Sequelize) {
    const lessons = [
      {
        id: 1,
        name: "Variáveis e Sintaxe Básica",
        previous_lesson: null,
        created_at: new Date(),
        updated_at: new Date(),
      },
      {
        id: 2,
        name: "Boolean, Strings e Sintaxe",
        previous_lesson: 1,
        created_at: new Date(),
        updated_at: new Date(),
      },
    ];

    await queryInterface.bulkInsert("lessons", lessons, {});
  },

  async down(queryInterface, Sequelize) {
    await queryInterface.bulkDelete("lessons", null, {});
  },
};

// "previousLesson": null always. fix bug
